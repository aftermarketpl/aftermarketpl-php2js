<?php

namespace Aftermarketpl\PHP2JS;

use PhpParser\ParserFactory;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;
use PhpParser\Node;

use Aftermarketpl\PHP2JS\Context\GlobalContext;
use Aftermarketpl\PHP2JS\Context\FunctionContext;
use Aftermarketpl\PHP2JS\Action\InitializeAction;
use Aftermarketpl\PHP2JS\Action\TypeAction;

class Parser
{
    protected $code;
    
    protected $environment;
    
    protected $indent;
    
    public function __construct(string $code, Environment $env)
    {
        $this->code = $code;
        $this->environment = $env;
        $this->indent = 0;
    }
    
    public function parse() : string
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $stmts = $parser->parse($this->code);
        $context = new GlobalContext();
        
        $this->guessTypes($stmts, $context);
        $return = $this->parseStatements($stmts, $context);
        print_r($context);
        return $return;
    }
    
    protected function guessTypes(array $stmts, Context &$context)
    {
        do
        {
            $context->clearDirty();
            foreach($stmts as $stmt)
                $this->guessType($stmt, $context);
        }
        while($context->isDirty());
    }
    
    protected function getIndent() : string
    {
        return str_repeat("    ", $this->indent);
    }
    
    protected function renderBlock(array $stmts, Context &$context, callable $more) : string
    {
        $result = $this->getIndent() . "{\n";
        $this->indent++;
        $result2 .= $this->parseStatements($stmts, $context);
        $additional = $more($context);
        foreach($additional as $line)
            $result .= $this->getIndent() . $line . "\n";
        $this->indent--;
        $result .= $result2;
        $result .= $this->getIndent() . "}";
        return $result;
    }
    
    protected function parseBlock(array $stmts, Context &$context) : string
    {
        return $this->renderBlock($stmts, $context, function(Context $context) { return array(); });
    }
    
    protected function parseStatements(array $stmts, Context &$context) : string
    {
        $result = "";
        foreach($stmts as $stmt)
            $result .= $this->parseStatement($stmt, $context);
        return $result;
    }
    
    protected function parseStatement(Node $stmt, Context &$context) : string
    {
        $result = $this->getIndent() . $this->parseNode($stmt, $context);
        $type = $stmt->getType();
        if(!in_array($type, ["Stmt_While", "Stmt_Do", "Stmt_If", "Stmt_Switch", "Stmt_Case", "Stmt_For", "Stmt_Foreach", "Stmt_Function", "Stmt_Global"]))
            $result .= ";";
        if(!in_array($type, ["Stmt_Global"]))
            return $result . "\n";
        else
            return "";
    }
    
    protected function parseExpressions(array $exprs, Context &$context) : string
    {
        $return = array();
        foreach($exprs as $expr)
        {
            $return[] = $this->parseNode($expr, $context);
        }
        return join(", ", $return);
    }

    protected function parseExpression(Node $expr, Context &$context) : string
    {
        $paren = $expr instanceof BinaryOp;
        
        $result = "";
        if($paren) $result .= "(";
        $result .= $this->parseNode($expr, $context);
        if($paren) $result .= ")";
        return $result;
    }
    
    protected function renderAssignmentOperator(string $op, Node $node, Context &$context) : string
    {
        return $this->parseNode($node->var, $context) . " " . $op . " " . $this->parseNode($node->expr, $context);
    }
    
    protected function renderBinaryOperator(string $op, Node $node, Context &$context) : string
    {
        return $this->parseExpression($node->left, $context) . " " . $op . " " . $this->parseExpression($node->right, $context);
    }
    
    protected function renderName(Node $node, Context &$context) : string
    {
        return join("\\", $node->parts);
    }
    
    protected function parseNode(Node $node, Context &$context, ?Action &$action = null) : string
    {
        $type = $node->getType();
        
        switch($type)
        {
            case "Stmt_Expression":
                return $this->parseExpression($node->expr, $context);

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
                    $return[] = $this->parseExpression($expr, $context);
                return "(" . join(" + ", $return) . ")";

            case "Scalar_EncapsedStringPart":
                return json_encode(strval($node->value));

            case "Expr_ConstFetch":
                $name = $this->renderName($node->name, $context);
                switch($name)
                {
                    case "true": return 1;
                    case "false": return "''";
                    default: return $name;
                }

            case "Expr_Variable":
                if(!is_string($node->name))
                    throw new \Exception("Cannot parse computed variable name");
                $context->addVariable($node->name);
                if($action) $action->onVariable($node->name);
                return $node->name;
            
            /*
             * Language constructs.
             */

            case "Stmt_Nop":
                return "";

            case "Stmt_Label":
                return "";

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
                    . $this->parseExpression($node->cond, $context) 
                    . ")\n"
                    . $this->parseBlock($node->stmts, $context);

            case "Stmt_If":
                $return = "if(" 
                    . $this->parseNode($node->cond, $context)
                    . ")\n"
                    . $this->parseBlock($node->stmts, $context);
                if(!empty($node->elseifs))
                    foreach($node->elseifs as $elseif)
                        $return .= $this->parseNode($elseif, $context);
                if(!empty($node->else))
                    $return .= $this->parseNode($node->else, $context);
                return $return;

            case "Stmt_ElseIf":
                return "else if(" 
                    . $this->parseNode($node->cond, $context) 
                    . ")\n"
                    . $this->parseBlock($node->stmts, $context);

            case "Stmt_Else":
                $return = "else\n"
                    . $this->parseBlock($node->stmts, $context);
                return $return;

            case "Stmt_Do":
                return "do\n"
                    . $this->parseBlock($node->stmts, $context)
                    . "while(" 
                    . $this->parseNode($node->cond, $context) 
                    . ");\n";

            case "Stmt_For":
                return "for(" 
                    . $this->parseExpressions($node->init, $context)
                    . "; "
                    . $this->parseExpressions($node->cond, $context)
                    . "; "
                    . $this->parseExpressions($node->loop, $context)
                    . ")\n"
                    . $this->parseBlock($node->stmts, $context);

            case "Stmt_Foreach":
                if($node->byRef)
                    throw new \Exception("Cannot assign values by reference");
                $source = $this->parseNode($node->expr, $context);
                $additional = array($this->parseNode($node->valueVar, $context) . " = " . $source . "[_tmp];");
                if($node->keyVar)
                    $additional[] = $this->parseNode($node->keyVar, $context) . " = __tmp;";
                return "for(let __tmp of " . $source . ")\n"
                    . $this->renderBlock($node->stmts, $context, function(Context $context) use($additional) { return $additional; });

            case "Stmt_Switch":
                return "switch(" 
                    . $this->parseNode($node->cond, $context) 
                    . ")\n"
                    . $this->parseBlock($node->cases, $context);

            case "Stmt_Case":
                if(empty($node->cond))
                    $return = "default:\n";
                else
                    $return = "case " . $this->parseNode($node->cond, $context) . ":\n";
                $this->indent++;
                foreach($node->stmts as $stmt)
                    $return .= $this->parseStatement($stmt, $context);
                $this->indent--;
                return $return;

            case "Stmt_Echo":
                $return = array();
                foreach($node->exprs as $expr)
                    $return[] = $this->parseNode($expr, $context);
                return "console.log(" . join(", ", $return) . ")";

            case "Expr_Print":
                return "console.log(" . $this->parseNode($node->expr, $context) . ")";

            case "Expr_List":
                $return = array();
                $action = new InitializeAction($context);
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
                        $return[] = $this->parseNode($item, $context, $action);
                    }
                }
                return "[" . join(", ", $return) . "]";

            case "Expr_Ternary":
                if(empty($node->if))
                    throw new \Exception("Ternary operator without second argument");
                else
                    return $this->parseNode($node->cond, $context) . " ? " . $this->parseNode($node->if, $context) . " : " . $this->parseNode($node->else, $context);

            case "Expr_ErrorSuppress":
                return $this->parseNode($node->expr, $context);

            /*
             * Built in operations.
             */

            case "Stmt_Unset":
                $return = array();
                foreach($node->vars as $expr)
                    $return[] = "delete " . $this->parseNode($expr, $context);
                return join("; ", $return);

            case "Expr_Isset":
                if(count($node->vars) == 1)
                    return "(typeof " . $this->parseExpression($node->vars[0], $context) . " !== 'undefined')";
                else
                    throw new \Exception("Multiple arguments for isset()");

            case "Expr_Empty":
                return "!Boolean(" . $this->parseNode($node->expr, $context) . ")";
            
            /*
             * Unary operators.
             */
            
            case "Expr_UnaryMinus":
                return "-" . $this->parseExpression($node->expr, $context);
            
            case "Expr_UnaryPlus":
                return "+" . $this->parseExpression($node->expr, $context);

            case "Expr_BooleanNot":
                return "!" . $this->parseExpression($node->expr, $context);
            
            case "Expr_BitwiseNot":
                return "~" . $this->parseExpression($node->expr, $context);
            
            case "Expr_PreDec":
                return "--" . $this->parseNode($node->var, $context);
            
            case "Expr_PreInc":
                return "++" . $this->parseNode($node->var, $context);
            
            case "Expr_PostDec":
                return $this->parseNode($node->var, $context) . "--";
            
            case "Expr_PostInc":
                return $this->parseNode($node->var, $context) . "++";
            
            /*
             * Assignment operators.
             */
             
            case "Expr_Assign":
                if($node->var instanceof ArrayDimFetch && empty($node->var->dim))
                    return "(" . $this->parseNode($node->var->var, $context) . ").push(" . $this->parseNode($node->expr, $context) . ")";
                $action = new InitializeAction($context);
                $left = $this->parseNode($node->var, $context, $action);
                $right = $this->parseNode($node->expr, $context);
                return $left . " = " . $right;

            case "Expr_AssignOp_Plus":
                return $this->renderAssignmentOperator("+=", $node, $context);

            case "Expr_AssignOp_Minus":
                return $this->renderAssignmentOperator("-=", $node, $context);

            case "Expr_AssignOp_Mul":
                return $this->renderAssignmentOperator("*=", $node, $context);

            case "Expr_AssignOp_Div":
                return $this->renderAssignmentOperator("/=", $node, $context);

            case "Expr_AssignOp_Mod":
                return $this->renderAssignmentOperator("%=", $node, $context);

            case "Expr_AssignOp_Concat":
                return $this->renderAssignmentOperator("+=", $node, $context);

            case "Expr_AssignOp_ShiftLeft":
                return $this->renderAssignmentOperator("<<=", $node, $context);

            case "Expr_AssignOp_ShiftRight":
                return $this->renderAssignmentOperator(">>=", $node, $context);

            case "Expr_AssignOp_BitwiseAnd":
                return $this->renderAssignmentOperator("&=", $node, $context);

            case "Expr_AssignOp_BitwiseOr":
                return $this->renderAssignmentOperator("|=", $node, $context);

            case "Expr_AssignOp_BitwiseXor":
                return $this->renderAssignmentOperator("^=", $node, $context);

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
                return $this->renderBinaryOperator($node->getOperatorSigil(), $node, $context);

            case "Expr_BinaryOp_Concat":
                return $this->renderBinaryOperator("+", $node, $context);

            case "Expr_BinaryOp_Coalesce":
                return $this->renderBinaryOperator("||", $node, $context);

            case "Expr_BinaryOp_LogicalAnd":
                return $this->renderBinaryOperator("&&", $node, $context);

            case "Expr_BinaryOp_LogicalOr":
                return $this->renderBinaryOperator("||", $node, $context);

            case "Expr_BinaryOp_Pow":
                return "Math.pow(" . $this->parseNode($node->left, $context) . ", " . $this->parseNode($node->right, $context) . ")";

            /*
             * Cast operators.
             */

            case "Expr_Cast_Int":
                return "parseInt(" . $this->parseNode($node->expr, $context) . ")";

            case "Expr_Cast_Double":
                return "parseFloat(" . $this->parseNode($node->expr, $context) . ")";

            case "Expr_Cast_String":
                return "String(" . $this->parseNode($node->expr, $context) . ")";

            case "Expr_Cast_Boolean":
                return "Boolean(" . $this->parseNode($node->expr, $context) . ")";

            case "Expr_Cast_Array":
                return "(function(x) { if(x instanceof Array) return x; else return [x]; })(" . $this->parseNode($node->expr, $context) . ")";

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
                    $return[] = $this->getIndent() . $this->parseNode($node->items[$i], $context);
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
                    return $this->parseNode($node->key, $context) . ": " . $this->parseNode($node->value, $context);
                else
                    return $this->parseNode($node->value, $context, $action);

            case "Expr_ArrayDimFetch":
                if(empty($node->dim))
                    throw new \Exception("Empty array dimension");
                return $this->parseExpression($node->var, $context) . "[" . $this->parseNode($node->dim, $context) . "]";

            /*
             * Function calls.
             */

            case "Expr_FuncCall":
                if(!($node->name instanceof Name))
                    throw new \Exception("Cannot use dynamic function names");
                $name = $this->renderName($node->name, $context);
                $args = array();
                foreach($node->args as $arg)
                    $args[] = $this->parseNode($arg, $context);
                if(!$context->getGlobal()->hasFunction($name))
                    return $this->environment->translateFunction($name, $args);
                else
                    return $name . "(" . join(", ", $args) . ")";

            case "Arg":
                if($node->byRef)
                    throw new \Exception("Cannot pass values by reference");
                if($node->unpack)
                    throw new \Exception("Cannot unpack values");
                return $this->parseNode($node->value, $context);

            /*
             * Function definitions.
             */

            case "Stmt_Function":
                $name = $node->name->name;
                $context->getGlobal()->addFunction($name);
                $newContext = new FunctionContext($context->getGlobal(), $context);
                $params = array();
                foreach($node->params as $arg)
                    $params[] = $this->parseNode($arg, $newContext);
                $additional = array();
                $cnt = 0;
                foreach($newContext->getParameters() as $param)
                    $additional[] = $param . " = __par" . (++$cnt) . ";";
                return "function " . $name . "(" . join(", ", $params) . ")\n"
                    . $this->renderBlock($node->stmts, $newContext, 
                        function(Context $context) use($additional) { 
                            $ret = $additional;
                            $vars = $context->getVariables();
                            if(!empty($vars)) array_unshift($ret, "var " . join(", ", $vars) . ";");
                            return $ret; 
                    });

            case "Param":
                if($node->byRef)
                    throw new \Exception("Cannot pass parameters by reference");
                $name = $node->var->name;
                return ($node->variadic ? "..." : "") . "__par" . $context->addParameter($name);

            case "Stmt_Return":
                if(empty($node->expr))
                    return "return";
                else
                    return "return " . $this->parseNode($node->expr, $context);

            case "Stmt_Global":
                if(!($context instanceOf FunctionContext))
                    throw new \Exception("Global keyword outside function");
                foreach($node->vars as $var)
                    $context->addGlobal($var->name);
                return "";


            /*
             * Unknown node types.
             */

            default:
                throw new \Exception("Cannot parse: $type");
        }
    }

    protected function chooseType(int $type1, int $type2) : int
    {
        if($type1 != Context::UNKNOWN)
            return $type1;
        else
            return $type2;
    }
    
    protected function convertType(?Node $node) : int
    {
        if($node instanceof Identifier)
        {
            switch($node->name)
            {
                case "bool":
                case "int":
                case "float":
                    return Context::NUMBER;
                case "string":
                    return Context::STRING;
                case "array":
                    return Context::ARRAY;
                case "callable":
                case "void":
                    return Context::UNKNOWN;
                default:
                    return Context::OBJECT;
            }
        }
        return Context::UNKNOWN;
    }
    
    protected function singleType(Node $node, Context &$context, int $type = Context::UNKNOWN) : int
    {
        $action = new TypeAction($context, $type);
        $type2 =  $this->guessType($node, $context, $action);
        return $type != Context::UNKNOWN ? $type : $type2;
    }

    protected function guessType(Node $node, Context &$context, ?Action $action = null) : int
    {
        $type = $node->getType();
        
        switch($type)
        {
            case "Stmt_Expression":
                return $this->guessType($node->expr, $context);

            /*
             * Simple elements.
             */
            case "Scalar_LNumber":
            case "Scalar_DNumber":
                return Context::NUMBER;

            case "Scalar_String":
            case "Scalar_Encapsed":
                return Context::STRING;

            case "Expr_Variable":
                if($action) $action->onVariable($node->name);
                return $context->getType($node->name);

            /*
             * Language constructs.
             */


            case "Stmt_While":
            case "Stmt_ElseIf":
            case "Stmt_Do":
            case "Stmt_Case":
                if(!empty($node->cond))
                    $this->guessType($node->cond, $context);
                foreach($node->stmts as $stmt)
                    $this->guessType($stmt, $context);
                return Context::UNKNOWN;

            case "Stmt_If":
                $this->guessType($node->cond, $context);
                foreach($node->stmts as $stmt)
                    $this->guessType($stmt, $context);
                if(!empty($node->elseifs))
                    foreach($node->elseifs as $elseif)
                        $this->guessType($elseif, $context);
                if(!empty($node->else))
                    $this->guessType($node->else, $context);
                return Context::UNKNOWN;

            case "Stmt_Else":
                foreach($node->stmts as $stmt)
                    $this->guessType($stmt, $context);
                return Context::UNKNOWN;

            case "Stmt_For":
                foreach($node->init as $stmt)
                    $this->guessType($stmt, $context);
                foreach($node->cond as $stmt)
                    $this->guessType($stmt, $context);
                foreach($node->loop as $stmt)
                    $this->guessType($stmt, $context);
                foreach($node->stmts as $stmt)
                    $this->guessType($stmt, $context);
                return Context::UNKNOWN;

            case "Stmt_Foreach":
                $this->guessType($node->expr, $context);
                if($node->keyVar)
                    $this->guessType($node->keyVar, $context);
                foreach($node->stmts as $stmt)
                    $this->guessType($stmt, $context);
                return Context::UNKNOWN;

            case "Stmt_Switch":
                $this->guessType($node->cond, $context);
                foreach($node->cases as $stmt)
                    $this->guessType($stmt, $context);
                return Context::UNKNOWN;

            case "Stmt_Echo":
                foreach($node->exprs as $stmt)
                    $this->guessType($stmt, $context);
                return Context::UNKNOWN;

            case "Stmt_Print":
                $this->guessType($node->expr, $context);
                return Context::UNKNOWN;

            case "Expr_List":
                foreach($node->items as $stmt)
                    $this->guessType($stmt, $context);
                return Context::ARRAY;

            case "Expr_Ternary":
                return $this->chooseType($this->guessType($node->if, $context), $this->guessType($node->else, $context));

            case "Expr_ErrorSuppress":
                return $this->guessType($node->expr, $context);

            /*
             * Built in operations.
             */

            case "Expr_Isset":
            case "Expr_Empty":
                return Context::NUMBER;

            /*
             * Unary operators.
             */
            
            case "Expr_UnaryMinus":
            case "Expr_UnaryPlus":
            case "Expr_BooleanNot":
            case "Expr_BitwiseNot":
                return $this->singleType($node->expr, $context, Context::NUMBER);
                
            case "Expr_PreDec":
            case "Expr_PreInc":
            case "Expr_PostDec":
            case "Expr_PostInc":
                return $this->singleType($node->var, $context, Context::NUMBER);
            
            /*
             * Assignment operators.
             */
             
            case "Expr_Assign":
            case "Expr_AssignOp_Plus":
                return $this->singleType($node->var, $context, $this->singleType($node->expr, $context));

            case "Expr_AssignOp_Minus":
            case "Expr_AssignOp_Mul":
            case "Expr_AssignOp_Div":
            case "Expr_AssignOp_Mod":
            case "Expr_AssignOp_ShiftLeft":
            case "Expr_AssignOp_ShiftRight":
            case "Expr_AssignOp_BitwiseAnd":
            case "Expr_AssignOp_BitwiseOr":
            case "Expr_AssignOp_BitwiseXor":
                return $this->chooseType($this->singleType($node->var, $context, Context::NUMBER), $this->singleType($node->expr, $context, Context::NUMBER));

            case "Expr_AssignOp_Concat":
                return Context::STRING;

            /*
             * Binary operators.
             */
             
            case "Expr_BinaryOp_Plus":
                $type = $this->singleType($node->left, $context);
                if($type != Context::UNKNOWN)
                    return $this->singleType($node->right, $context, $type);
                else
                    return $this->singleType($node->left, $context, $this->singleType($node->right, $context));

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
            case "Expr_BinaryOp_Pow":
                return $this->chooseType($this->singleType($node->left, $context, Context::NUMBER), $this->singleType($node->right, $context, Context::NUMBER));

            case "Expr_BinaryOp_Concat":
                return $this->chooseType($this->singleType($node->left, $context, Context::STRING), $this->singleType($node->right, $context, Context::STRING));

            case "Expr_BinaryOp_Coalesce":
                return $this->chooseType($this->singleType($node->left, $context), $this->singleType($node->right, $context));

            case "Expr_BinaryOp_LogicalAnd":
            case "Expr_BinaryOp_LogicalOr":
                return $this->chooseType($this->singleType($node->left, $context), $this->singleType($node->right, $context));

            /*
             * Cast operators.
             */

            case "Expr_Cast_Int":
            case "Expr_Cast_Double":
            case "Expr_Cast_Boolean":
                $this->guessType($node->expr, $context);
                return Context::NUMBER;

            case "Expr_Cast_String":
                $this->guessType($node->expr, $context);
                return Context::STRING;

            case "Expr_Cast_Array":
                $this->guessType($node->expr, $context);
                return Context::ARRAY;

            /*
             * Arrays.
             */

            case "Expr_Array":
                foreach($node->items as $item)
                    $this->guessType($item, $context);
                return Context::ARRAY;

            case "Expr_ArrayDimFetch":
                if(!empty($node->dim))
                    $this->guessType($node->dim, $context);
                return Context::UNKNOWN;

            /*
             * Function calls.
             */

            case "Expr_FuncCall":
                if($node->name instanceof Name)
                {
                    $name = $this->renderName($node->name, $context);
                    foreach($node->args as $arg)
                        $this->guessType($arg, $context);
                    if(!$context->getGlobal()->hasFunction($name))
                        return Context::UNKNOWN;
                    else
                        return $context->getGlobal()->functionReturnType($name);
                }
                return Context::UNKNOWN;

            case "Arg":
                $this->guessType($node->value, $context);
                return Context::UNKNOWN;

            /*
             * Function definitions.
             */

            case "Stmt_Function":
                $name = $node->name->name;
                $newContext = new FunctionContext($context->getGlobal(), $context);
                $params = array();
                foreach($node->params as $arg)
                    $this->guessType($arg, $newContext);
                $this->guessTypes($node->stmts, $newContext);
                $context->getGlobal()->addFunction($name, $this->chooseType(
                    $this->convertType($node->returnType),
                    $newContext->getReturnType()));
                return Context::UNKNOWN;

            case "Param":
                $name = $node->var->name;
                $context->addParameter($name, $node->variadic ? Context::ARRAY : $this->convertType($node->type));
                return Context::UNKNOWN;

            case "Stmt_Return":
                if(!empty($node->expr))
                {
                    $type = $this->guessType($node->expr, $context);
                    $context->setReturnType($type);
                    return $type;
                }
                return Context::UNKNOWN;

            case "Stmt_Global":
                if($context instanceOf FunctionContext)
                {
                    foreach($node->vars as $var)
                        $context->addGlobal($var->name);
                }
                return Context::UNKNOWN;

            /*
             * Unknown node types.
             */

            default:
                return Context::UNKNOWN;
        }
    }
}

?>