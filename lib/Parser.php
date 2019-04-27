<?php

namespace Aftermarketpl\PHP2JS;

use PhpParser\ParserFactory;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Name;

class Parser
{
    protected $code;
    
    protected $environment;
    
    protected $indent;
    
    public function __construct($code, $env)
    {
        $this->code = $code;
        $this->environment = $env;
        $this->indent = 0;
    }
    
    public function parse()
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($this->code);
        
        return $this->parseStatements($ast);
    }
    
    protected function getIndent()
    {
        return str_repeat("    ", $this->indent);
    }
    
    protected function renderBlock($stmts, $additional = null)
    {
        $result = $this->getIndent() . "{\n";
        $this->indent++;
        if(!empty($additional))
        {
            foreach($additional as $line)
                $result .= $this->getIndent() . $line . "\n";
        }
        $result .= $this->parseStatements($stmts);
        $this->indent--;
        $result .= $this->getIndent() . "}";
        return $result;
    }
    
    protected function parseBlock($stmts)
    {
        return $this->renderBlock($stmts);
    }
    
    protected function parseStatements($stmts)
    {
        $result = "";
        foreach($stmts as $stmt)
            $result .= $this->parseStatement($stmt);
        return $result;
    }
    
    protected function parseStatement($stmt)
    {
        $result = $this->getIndent() . $this->parseNode($stmt);
        $type = $stmt->getType();
        if(!in_array($type, ["Stmt_While", "Stmt_Do", "Stmt_If", "Stmt_Switch", "Stmt_Case", "Stmt_For", "Stmt_Foreach"]))
            $result .= ";";
        return $result . "\n";
    }
    
    protected function parseExpressions($exprs)
    {
        $return = array();
        foreach($exprs as $expr)
        {
            $return[] = $this->parseExpression($expr);
        }
        return join(", ", $return);
    }

    protected function parseExpression($expr, $noparen = false)
    {
        $paren = !$noparen && $expr instanceof BinaryOp;
        
        $result = "";
        if($paren) $result .= "(";
        $result .= $this->parseNode($expr);
        if($paren) $result .= ")";
        return $result;
    }
    
    protected function renderAssignmentOperator($op, $node)
    {
        return $this->parseExpression($node->var, true) . " " . $op . " " . $this->parseExpression($node->expr, true);
    }
    
    protected function renderBinaryOperator($op, $node)
    {
        return $this->parseExpression($node->left) . " " . $op . " " . $this->parseExpression($node->right);
    }
    
    protected function renderName($node)
    {
        return join("\\", $node->parts);
    }
    
    protected function parseNode($node)
    {
        $type = $node->getType();
        
        switch($type)
        {
            case "Stmt_Expression":
                return $this->parseExpression($node->expr);

            /*
             * Simple elements.
             */
            case "Scalar_LNumber":
                return intval($node->value);

            case "Scalar_DNumber":
                return floatval($node->value);

            case "Scalar_String":
                return json_encode(strval($node->value));

            case "Scalar_Encapsed":
                $return = array();
                foreach($node->parts as $expr)
                    $return[] = $this->parseExpression($expr);
                return "(" . join(" + ", $return) . ")";

            case "Scalar_EncapsedStringPart":
                return json_encode(strval($node->value));

            case "Expr_ConstFetch":
                $name = $this->renderName($node->name);
                switch($name)
                {
                    case "true": return 1;
                    case "false": return "''";
                    default: return $name;
                }

            case "Expr_Variable":
                if(!is_string($node->name))
                    throw new \Exception("Cannot parse computed variable name");
                return $node->name;
            
            /*
             * Language constructs.
             */

            case "Stmt_Nop":
                return "";

            case "Stmt_Label":
                return "";

            case "Stmt_Return":
                if(empty($node->expr))
                    return "return";
                else
                    return "return " . $this->parseExpression($node->expr, true);

            case "Stmt_Break":
                if(!empty($node->num))
                    throw new \Exception("Cannot break with an argument");
                return "break";

            case "Stmt_Continue":
                if(!empty($node->num))
                    throw new \Exception("Cannot continue with an argument");
                return "continue";

            case "Stmt_While":
                return "while(" 
                    . $this->parseExpression($node->cond, true) 
                    . ")\n"
                    . $this->parseBlock($node->stmts);

            case "Stmt_If":
                $return = "if(" 
                    . $this->parseExpression($node->cond, true) 
                    . ")\n"
                    . $this->parseBlock($node->stmts);
                if(!empty($node->elseifs))
                    foreach($node->elseifs as $elseif)
                        $return .= $this->parseNode($elseif);
                if(!empty($node->else))
                    $return .= $this->parseNode($node->else);
                return $return;

            case "Stmt_ElseIf":
                return "else if(" 
                    . $this->parseExpression($node->cond, true) 
                    . ")\n"
                    . $this->parseBlock($node->stmts);

            case "Stmt_Else":
                $return = "else\n"
                    . $this->parseBlock($node->stmts);
                if(!empty($node->else))
                    $return .= $this->parseNode($node->else);
                return $return;

            case "Stmt_Do":
                return "do\n"
                    . $this->parseBlock($node->stmts)
                    . "while(" 
                    . $this->parseExpression($node->cond, true) 
                    . ");\n";

            case "Stmt_For":
                return "for(" 
                    . $this->parseExpressions($node->init)
                    . "; "
                    . $this->parseExpressions($node->cond)
                    . "; "
                    . $this->parseExpressions($node->loop)
                    . ")\n"
                    . $this->parseBlock($node->stmts);

            case "Stmt_Foreach":
                if($node->byRef)
                    throw new \Exception("Cannot assign values by reference");
                $source = $this->parseExpression($node->expr);
                $additional = array($this->parseExpression($node->valueVar) . " = " . $source . "[_tmp];");
                if($node->keyVar)
                    $additional[] = $this->parseExpression($node->keyVar) . " = _tmp;";
                return "for(_tmp in " . $source . ")\n"
                    . $this->renderBlock($node->stmts, $additional);

            case "Stmt_Switch":
                return "switch(" 
                    . $this->parseExpression($node->cond, true) 
                    . ")\n"
                    . $this->parseBlock($node->cases);

            case "Stmt_Case":
                if(empty($node->cond))
                    $return = "default:\n";
                else
                    $return = "case " . $this->parseExpression($node->cond, true) . ":\n";
                $this->indent++;
                foreach($node->stmts as $stmt)
                    $return .= $this->parseStatement($stmt);
                $this->indent--;
                return $return;

            case "Stmt_Echo":
                $return = array();
                foreach($node->exprs as $expr)
                    $return[] = $this->parseExpression($expr, true);
                return "console.log(" . join(", ", $return) . ")";
            case "Expr_Print":
                return "console.log(" . $this->parseExpression($node->expr, true) . ")";

            case "Expr_List":
                $return = array();
                foreach($node->items as $item)
                {
                    if(empty($item))
                    {
                        $return[] = "";
                    }
                    else
                    {
                        if(!empty($item->key))
                            throw new \Exception("Array key assignment not allowed");
                        $return[] = $this->parseNode($item);
                    }
                }
                return "[" . join(", ", $return) . "]";

            case "Expr_Ternary":
                if(empty($node->if))
                    throw new \Exception("Ternary operator without second argument");
                else
                    return $this->parseExpression($node->cond) . " ? " . $this->parseExpression($node->if) . " : " . $this->parseExpression($node->else);

            case "Expr_ErrorSuppress":
                return $this->parseExpression($node->expr);

            /*
             * Built in operations.
             */

            case "Stmt_Unset":
                $return = array();
                foreach($node->vars as $expr)
                    $return[] = "delete " . $this->parseExpression($expr, true);
                return join("; ", $return);

            case "Expr_Isset":
                if(count($node->vars) == 1)
                    return "(typeof " . $this->parseExpression($node->vars[0]) . " !== 'undefined')";
                else
                    throw new \Exception("Multiple arguments for isset()");

            case "Expr_Empty":
                return "!Boolean(" . $this->parseExpression($node->expr) . ")";
            
            /*
             * Unary operators.
             */
            
            case "Expr_UnaryMinus":
                return "-" . $this->parseExpression($node->expr);
            
            case "Expr_UnaryPlus":
                return "+" . $this->parseExpression($node->expr);

            case "Expr_BooleanNot":
                return "!" . $this->parseExpression($node->expr);
            
            case "Expr_BitwiseNot":
                return "~" . $this->parseExpression($node->expr);
            
            case "Expr_PreDec":
                return "--" . $this->parseExpression($node->var, true);
            
            case "Expr_PreInc":
                return "++" . $this->parseExpression($node->var, true);
            
            case "Expr_PostDec":
                return $this->parseExpression($node->var, true) . "--";
            
            case "Expr_PostInc":
                return $this->parseExpression($node->var, true) . "++";
            
            /*
             * Assignment operators.
             */
             
            case "Expr_Assign":
                if($node->var instanceof ArrayDimFetch && empty($node->var->dim))
                    return "(" . $this->parseExpression($node->var->var) . ").push(" . $this->parseExpression($node->expr) . ")";
                return $this->renderAssignmentOperator("=", $node);

            case "Expr_AssignOp_Plus":
                return $this->renderAssignmentOperator("+=", $node);

            case "Expr_AssignOp_Minus":
                return $this->renderAssignmentOperator("-=", $node);

            case "Expr_AssignOp_Mul":
                return $this->renderAssignmentOperator("*=", $node);

            case "Expr_AssignOp_Div":
                return $this->renderAssignmentOperator("/=", $node);

            case "Expr_AssignOp_Mod":
                return $this->renderAssignmentOperator("%=", $node);

            case "Expr_AssignOp_Concat":
                return $this->renderAssignmentOperator("+=", $node);

            case "Expr_AssignOp_ShiftLeft":
                return $this->renderAssignmentOperator("<<=", $node);

            case "Expr_AssignOp_ShiftRight":
                return $this->renderAssignmentOperator(">>=", $node);

            case "Expr_AssignOp_BitwiseAnd":
                return $this->renderAssignmentOperator("&=", $node);

            case "Expr_AssignOp_BitwiseOr":
                return $this->renderAssignmentOperator("|=", $node);

            case "Expr_AssignOp_BitwiseXor":
                return $this->renderAssignmentOperator("^=", $node);

            /*
             * Binary operators.
             */
             
            case "Expr_BinaryOp_Plus":
            case "Expr_BinaryOp_Minus":
            case "Expr_BinaryOp_Mul":
            case "Expr_BinaryOp_Div":
            case "Expr_BinaryOp_Mod":
            case "Expr_BinaryOp_ShiftLeft":
            case "Expr_BinaryOp_ShiftRight":
            case "Expr_BinaryOp_BitwiseAnd":
            case "Expr_BinaryOp_BitwiseOr":
            case "Expr_BinaryOp_BitwiseXor":
            case "Expr_BinaryOp_BooleanAnd":
            case "Expr_BinaryOp_BooleanOr":
            case "Expr_BinaryOp_Equal":
            case "Expr_BinaryOp_NotEqual":
            case "Expr_BinaryOp_Greater":
            case "Expr_BinaryOp_Smaller":
            case "Expr_BinaryOp_GreaterOrEqual":
            case "Expr_BinaryOp_SmallerOrEqual":
            case "Expr_BinaryOp_Identical":
            case "Expr_BinaryOp_NotIdentical":
                return $this->renderBinaryOperator($node->getOperatorSigil(), $node);

            case "Expr_BinaryOp_Concat":
                return $this->renderBinaryOperator("+", $node);

            case "Expr_BinaryOp_Coalesce":
                return $this->renderBinaryOperator("||", $node);

            case "Expr_BinaryOp_LogicalAnd":
                return $this->renderBinaryOperator("&&", $node);

            case "Expr_BinaryOp_LogicalOr":
                return $this->renderBinaryOperator("||", $node);

            case "Expr_BinaryOp_Pow":
                return "Math.pow(" . $this->parseExpression($node->left, true) . ", " . $this->parseExpression($node->right, true) . ")";

            /*
             * Cast operators.
             */

            case "Expr_Cast_Int":
                return "parseInt(" . $this->parseExpression($node->expr, true) . ")";

            case "Expr_Cast_Double":
                return "parseFloat(" . $this->parseExpression($node->expr, true) . ")";

            case "Expr_Cast_String":
                return "String(" . $this->parseExpression($node->expr, true) . ")";

            case "Expr_Cast_Boolean":
                return "Boolean(" . $this->parseExpression($node->expr, true) . ")";

            case "Expr_Cast_Array":
                return "(function(x) { if(x instanceof Array) return x; else return [x]; })(" . $this->parseExpression($node->expr, true) . ")";

            /*
             * Arrays.
             */

            case "Expr_Array":
                if(!count($node->items)) return "[]";
                $return = array();
                $objects = false;
                $this->indent++;
                for($i = 0; $i < count($node->items); $i++)
                {
                    if(!empty($node->items[$i]->key)) $objects = true;
                    $return[] = $this->getIndent() . $this->parseNode($node->items[$i]);
                }
                $this->indent--;
                if(!$objects)
                    return "[\n" . join(",\n", $return) . "\n" . $this->getIndent() . "]";
                else
                    return "{\n" . join(",\n", $return) . "\n" . $this->getIndent() . "}";

            case "Expr_ArrayItem":
                if($node->byRef)
                    throw new \Exception("Cannot assign array by reference");
                if(!empty($node->key))
                    return $this->parseExpression($node->key) . ": " . $this->parseExpression($node->value);
                else
                    return $this->parseExpression($node->value);

            case "Expr_ArrayDimFetch":
                if(empty($node->dim))
                    throw new \Exception("Empty array dimension");
                return $this->parseExpression($node->var) . "[" . $this->parseExpression($node->dim, true) . "]";

            /*
             * Function calls.
             */

            case "Expr_FuncCall":
                if(!($node->name instanceof Name))
                    throw new \Exception("Cannot use dynamic function names");
                $name = $this->renderName($node->name);
                $args = array();
                foreach($node->args as $arg)
                    $args[] = $this->parseExpression($arg, true);
                return $this->environment->translateFunction($name, $args);

            case "Arg":
                if($node->byRef)
                    throw new \Exception("Cannot pass values by reference");
                if($node->unpack)
                    throw new \Exception("Cannot unpack values");
                return $this->parseExpression($node->value, true);

            /*
             * Unknown node types.
             */

            default:
                throw new \Exception("Cannot parse: $type");
        }
    }
}

?>