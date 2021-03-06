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
    public function testTranslation($php, $js, $file)
    {
        $resultPHP = eval($php);

        $exec = new PhpExecJs();
        $resultJS = $exec->evalJs("(function() { " . $js . "})()");

        $out = pathinfo($file, PATHINFO_DIRNAME) . "/" . pathinfo($file, PATHINFO_FILENAME) . ".res";
        $f = fopen($out, "w");
        fputs($f, json_encode($resultPHP));
        fclose($f);
        
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
                $js = Translator::translate("<?php\n" . $php . "?>");

                $out = $path . "/" . pathinfo($file, PATHINFO_DIRNAME) . "/" . pathinfo($file, PATHINFO_FILENAME) . ".out";
                $f = fopen($out, "w");
                fputs($f, $js);
                fclose($f);
                
                $tests[$file] = array($php, $js, $path . "/" . $file);
            }
        }
        
        return $tests;
    }
}

?>