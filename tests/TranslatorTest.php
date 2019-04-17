<?php 

use PHPUnit\Framework\TestCase;

use Nacmartin\PhpExecJs\PhpExecJs;

use Aftermarketpl\PHP2JS\Translator;

class TranslatorTest extends TestCase
{
    /**
     * @dataProvider translationProvider
     */
    public function testTranslation($php)
    {
        $resultPHP = eval("return " . $php);

        $translator = new Translator("<?php\n" . $php . "?>");
        $js = $translator->getResult($php);
        $exec = new PhpExecJs();
        $resultJS = $exec->evalJs($js);
        
        $this->assertEquals($resultPHP, $resultJS);
    }
    
    public function translationProvider()
    {
        return array(
            "math" => array("1+2;"),
        );
    }
}

?>