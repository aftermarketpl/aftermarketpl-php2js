<?php 

use PHPUnit\Framework\TestCase;
use Nacmartin\PhpExecJs\PhpExecJs;
use Aftermarketpl\PHP2JS\Translator;
use Pop\Dir\Dir;

class TranslatorTest extends TestCase
{
    /**
     * @dataProvider translationProvider
     */
    public function testTranslation($php, $js)
    {
        $resultPHP = eval($php);

        $exec = new PhpExecJs();
        $resultJS = $exec->evalJs("(function() { " . $js . "})()");
        
        $this->assertEquals($resultPHP, $resultJS);
    }
    
    public function translationProvider()
    {
        $path = basename(dirname(__FILE__) . "/../tests");
        $dir = new Dir($path, array(
            "recursive" => true,
            "filesOnly" => true,
            "relative" => true,
        ));
        
        $tests = array();
        foreach($dir->getFiles() as $file)
        {
            if(pathinfo($file, PATHINFO_EXTENSION) == "in")
            {
                $php = file_get_contents($path . "/" . $file);
                $translator = new Translator("<?php\n" . $php . "?>");
                $js = $translator->getResult();

                $out = $path . "/" . pathinfo($file, PATHINFO_DIRNAME) . "/" . pathinfo($file, PATHINFO_FILENAME) . ".out";
                $f = fopen($out, "w");
                fputs($f, $js);
                fclose($f);
                
                $tests[$file] = array($php, $js);
            }
        }
        
        return $tests;
    }
}

?>