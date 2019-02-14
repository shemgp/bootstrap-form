<?php

use Watson\BootstrapForm\BootstrapForm;
use PHPUnit\Framework\TestCase;
use Collective\Html\FormBuilder;

use Illuminate\Http\Request;
use Collective\Html\HtmlBuilder;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Collection;

require 'vendor/autoload.php';

define('HOSTNAME', 'localhost:8077');

function asset($url, $value=null)
{
    return 'http://'.HOSTNAME.$url;
}

class SelectizeFormTest extends PHPUnit_Extensions_Selenium2TestCase
{

    public function setUp()
    {
        $this->hostname = HOSTNAME;
        $this->dir = '/tmp/'.__CLASS__;
        shell_exec('mkdir -p '.$this->dir);
        chdir($this->dir);
        shell_exec('ps -AfH | grep "'.$this->hostname.'" | grep -v grep | awk "{print \\$2}" | xargs kill 2> /dev/null');
        $this->pid = exec('nohup php -S '.$this->hostname.' > /dev/null 2> /dev/null & echo "$!"');

        $js = file_get_contents('https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.6/js/standalone/selectize.min.js');
        shell_exec('mkdir -p '.$this->dir.'/bower_components/selectize/dist/js/standalone/');
        file_put_contents($this->dir.'/bower_components/selectize/dist/js/standalone/selectize.js', $js);

        $file = file_get_contents('https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.6/css/selectize.bootstrap3.css');
        shell_exec('mkdir -p '.$this->dir.'/bower_components/selectize/dist/css/');
        file_put_contents($this->dir.'/bower_components/selectize/dist/css/selectize.bootstrap3.css', $file);

        $this->setBrowser('chrome');
        $this->setBrowserUrl('http://'.$this->hostname.'/');

        $this->setDesiredCapabilities(['chromeOptions' =>['args' => ['--headless']]]);

        $this->urlGenerator = new UrlGenerator(new RouteCollection, Request::create('/foo', 'GET'));
        $this->htmlBuilder = new HtmlBuilder($this->urlGenerator);
        $this->formBuilder =  new FormBuilder($this->htmlBuilder, $this->urlGenerator, 'abc');

        $this->htmlBuilderMock = Mockery::mock('Collective\Html\HtmlBuilder');
        $this->configMock = Mockery::mock('Illuminate\Contracts\Config\Repository')->shouldDeferMissing();
        $this->sessionMock = Mockery::mock('Illuminate\Session\SessionManager')->shouldDeferMissing();

        $this->bootstrapForm = new BootstrapForm(
            $this->htmlBuilderMock,
            $this->formBuilder,
            $this->configMock,
            $this->sessionMock
        );
    }

    public function tearDown()
    {
        shell_exec('kill '. $this->pid. '; rm -Rf '.$this->dir);
    }

    private function setupIndex($subfolder, $selectize)
    {
        $this->path = $this->dir.'/'.$subfolder;
        shell_exec('mkdir -p '.$this->path);

        $index = '<html><head>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/headjs/1.0.3/head.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.6/js/standalone/selectize.min.js"></script>
            <form action="http://'.$this->hostname.'/'.$subfolder.'/submit.php" host="GET">'.
                $selectize.
                '<button type="submit" id="go">go</button>
            </form></html>';
        file_put_contents($this->path.'/index.php', $index);
    }

    private function setupAjaxReturn($data, $whole = false)
    {
        if ($whole)
        {
            $json = json_encode($data);
        }
        else
        {
            $json = json_encode([
                'results' => $data,
                'pagination' => [
                    'more' => false
                ],
                'pass_back' => null
            ]);
        }
        file_put_contents($this->path.'/ajax.php', $json);
    }

    private function setupSubmit()
    {
        $submit = '<?php
            $return = array_merge($_POST, $_GET);
            echo json_encode($return);';
        file_put_contents($this->path.'/submit.php', $submit);
    }

    private function doEnterValueTest($subfolder, $name, $value)
    {
        $this->url('http://'.$this->hostname.'/'.$subfolder.'/index.php');
        sleep(1);
        $element = $this->byCssSelector('#'.$name.' + div');
        $element->click();
        $this->keys($value."\n");
        $this->execute(['script' => '$("#go").focus();', 'args' => []]);
        $this->clickOnElement('go');

        return strip_tags($this->source());
    }

    private function doMultipleFormEnterValueTest($subfolder, $names, $value)
    {
        $this->url('http://'.$this->hostname.'/'.$subfolder.'/index.php');
        sleep(1);
        foreach($names as $name)
        {
            $element = $this->byCssSelector('#'.$name.' + div');
            $element->click();
            $this->keys($value."\n");
        }
        $this->execute(['script' => '$("#go").focus();', 'args' => []]);
        $this->clickOnElement('go');

        return strip_tags($this->source());
    }

    private function doCreateValueTest($subfolder, $name, $value)
    {
        $this->url('http://'.$this->hostname.'/'.$subfolder.'/index.php');
        sleep(1);
        $element = $this->byCssSelector('#'.$name.' + div');
        $element->click();
        $this->keys($value."\t");
        $this->execute(['script' => '$("#go").focus();', 'args' => []]);
        $this->clickOnElement('go');

        return strip_tags($this->source());
    }

    private function doCreateValuesTest($subfolder, $name, $values)
    {
        $name = preg_replace('/[\[\]]/', '_', $name);

        $this->url('http://'.$this->hostname.'/'.$subfolder.'/index.php');
        sleep(1);
        $element = $this->byCssSelector('#'.$name.' + div');
        $element->click();
        foreach($values as $value)
        {
            $this->keys($value."\t");
            $this->execute(['script' => '$("#'.$name.'-selectized").focus();', 'args' => []]);
        }
        $this->execute(['script' => '$("#go").focus();', 'args' => []]);
        $this->clickOnElement('go');

        return strip_tags($this->source());
    }

    private function doWithValueTest($subfolder)
    {
        $this->url('http://'.$this->hostname.'/'.$subfolder.'/index.php');
        sleep(1);
        $this->execute(['script' => '$("#go").focus();', 'args' => []]);
        $this->clickOnElement('go');

        return strip_tags($this->source());
    }

    /** @test */
    public function testSelectizeWithAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test_id', [], null, ['url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);

        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"]]);
        $this->setupSubmit();
        $return = $this->doEnterValueTest($subfolder, 'test_id', 'test');

        $this->assertEquals(['test_id' => 1], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeWithoutAjax()
    {
        $selectize = $this->bootstrapForm->sselectize('test_id', ['1' => 'abc', '2' => 'def'], null);

        $subfolder = __FUNCTION__;
        $this->setupIndex($subfolder, $selectize);
        $this->setupSubmit();

        $return = $this->doEnterValueTest($subfolder, 'test_id', 'abc');

        $this->assertEquals(['test_id' => 1], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeWithValueAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test', [], 'hellow', ['url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);

        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"], ['id' => 2, "name" => "hellow"]]);
        $this->setupSubmit();
        $return = $this->doWithValueTest($subfolder);

        $this->assertEquals(['test' => 'hellow'], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeWithIdValueAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test', [], 2, ['field' => 'id', 'url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);

        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"], ['id' => 2, "name" => "hellow"]]);
        $this->setupSubmit();
        $return = $this->doWithValueTest($subfolder);

        $this->assertEquals(['test' => 2], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeWithIdValueInNameAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test_id', [], 2, ['url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);

        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"], ['id' => 2, "name" => "hellow"]]);
        $this->setupSubmit();
        $return = $this->doWithValueTest($subfolder);

        $this->assertEquals(['test_id' => 2], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeWithValue()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test', ['1' => "abc", 2 => "hellow"], 2);

        $this->setupIndex($subfolder, $selectize);
        $this->setupSubmit();
        $return = $this->doWithValueTest($subfolder);

        $this->assertEquals(['test' => 2], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeMultipleValues()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test[]', [1 => "abc", 2 => "hellow", 3 => "xyz"], [2,1], ['multiple' => true]);
        $this->setupIndex($subfolder, $selectize);
        $this->setupSubmit();

        $return = $this->doWithValueTest($subfolder);

        $this->assertEquals(['test' => [1,2]], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeMultipleIdValuesWithAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test[]', [], [2,1], ['multiple' => true, 'url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);
        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"], ['id' => 2, "name" => "hellow"], ['id' => 3, 'name' => "xyz"]]);
        $this->setupSubmit();
        $return = $this->doWithValueTest($subfolder);

        $this->assertEquals(['test' => ['2','1']], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeMultipleValuesWithAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test[]', [], ['test','xyz'], ['key' => 'name', 'multiple' => true, 'url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);
        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"], ['id' => 2, "name" => "hellow"], ['id' => 3, 'name' => "xyz"]]);
        $this->setupSubmit();
        $return = $this->doWithValueTest($subfolder);

        $this->assertEquals(['test' => ['test', 'xyz']], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeCreateValueAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test', [], null, ['create' => true, 'url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);
        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"], ['id' => 2, "name" => "hellow"], ['id' => 3, 'name' => "xyz"]]);
        $this->setupSubmit();
        $return = $this->doCreateValueTest($subfolder, 'test', 'new');

        $this->assertEquals(['test' => 'new'], json_decode($return, true));
    }

    /** @test */
    public function testSelectizeCreateMultipleValueAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test[]', [], null, ['create' => true, 'multiple' => true, 'url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);
        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"], ['id' => 2, "name" => "hellow"], ['id' => 3, 'name' => "xyz"]]);
        $this->setupSubmit();
        $return = $this->doCreateValuesTest($subfolder, 'test[]', ['new', 'york']);

        $this->assertEquals(['test' => ['new', 'york']], json_decode($return, true));
    }

    /** @test */
    public function testMultipleSelectizeWithAjax()
    {
        $subfolder = __FUNCTION__;

        $selectize = $this->bootstrapForm->sselectize('test_id', [], null, ['url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php'])
        .$this->bootstrapForm->sselectize('test2_id', [], null, ['url' => 'http://'.$this->hostname.'/'.$subfolder.'/ajax.php']);

        $this->setupIndex($subfolder, $selectize);
        $this->setupAjaxReturn([["id" => 1, "name" => "test"]]);
        $this->setupSubmit();
        $return = $this->doMultipleFormEnterValueTest($subfolder, ['test_id', 'test2_id'], 'test');

        $this->assertEquals(['test_id' => 1, 'test2_id' => 1], json_decode($return, true));
    }

}
