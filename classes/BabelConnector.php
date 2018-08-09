<?php
namespace Grav\Plugin\Babel;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Symfony\Component\Yaml\Yaml;
use Grav\Common\Language;
use Grav\Plugin\Babel\Babel;

class BabelConnector extends \PDO
{
    protected $once = false;
    
    public function __construct()
    {

    }

    public function getAttribute($attribute)
    {
        return false;
    }

    public function query($query)
    {
        $counter = 0;
        $results = [];

        $config = Grav::instance()['config'];
        $filter = $config->get('plugins.babel.filter');
        $default_process = $config->get('plugins.babel.index_page_by_default');
        $gbabel = new Babel();

        $codes = Grav::instance()['config']->get('plugins.babel.translation_sets', ['en']);
        
        $babeldefinitions = [];
        foreach($codes as $code => $langdef) {
            $babels = Grav::instance()['languages']->get($langdef);
            if (is_array($babels) && count($babels)) {
                $this->runBabelDefs($babeldefinitions, $babels);
            }
        }
        
        $babelobjects = [];
        foreach($codes as $code => $langdef) {
            foreach($babeldefinitions as $definition) {
                $babelobjects[] = $this->createBabelDef($definition, $langdef);
            }
        }

        $gbabel->trackBabelizedVariables();
        
        $theme_variables = $gbabel->trackThemeVariables();
        
        
        foreach ($babelobjects as $babelobj) {
                $counter++;
                $process = $default_process;
                try {
                    $fields = $gbabel->indexBabelData($babelobj);
                    
                    $results[] = (array) $fields;
                    echo("Added $counter $route\n");
                } catch (\Exception $e) {
                    echo($e->getMessage());
                    continue;
                }
            }
        
        return new BabelResultObject($results);
    }
    
    public function runBabelDefs(&$babeldefinitions, $babels, $level = 0, $levels = []) {        
        $excludes = ["INFLECTOR_PLURALS", "INFLECTOR_SINGULAR"];
        foreach($babels as $key => $babel) {
            if (!is_array($babel)) {
                if ($level == 0) {
                    $id = 'unclassified.' . $key;
                } else {
                    $id = implode('.', $levels) . '.' . $key;
                }                                      
                if (!in_array($id, $babeldefinitions)) {
                    $babeldefinitions[$id] = $id;
                }
            } elseif (is_array($babel)) {
                if (in_array($key, $excludes)) {
                    continue;
                }            
                $levels[$level] = $key;
                $this->runBabelDefs($babeldefinitions, $babel, $level + 1, $levels);                
            }
        }
    }

    private function createBabelDef($definition, $code) {
            $babelobj = new \stdClass();
            $id = $code . '.' . $definition;
            $babelobj->id = $id;
            $babelobj->route = $definition;
            $babelobj->domain = explode('.', $definition)[0];
            $babelobj->language = $code;
            $definition = str_replace('unclassified.', '', $definition);
            $translation = Grav::instance()['language']->translate($definition, [$code]);
            
            if ($translation == $definition || !$translation) {
                $babelobj->status = 0;
            } else {
                $babelobj->status = 1;
            }
            $babelobj->definition = $translation;
            
            $codes = Grav::instance()['config']->get('plugins.babel.translation_sets', ['en']);
            $translations = [];
            foreach($codes as $langdef) {
                if ($langdef != $code) {
                    //$definition = str_replace('unclassified.', '', $definition);
                    $translation = Grav::instance()['language']->translate($definition, [$langdef]);
                    if ($translation != $definition && $translation) {
                        $rtl = Babel::isRtl($langdef) ? 'RTL' : 'LTR';
                        $translations[$langdef] = [$translation, $rtl];
                    }
                }
            }
            $babelobj->translations = json_encode($translations, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            $babelobj->rtl = Babel::isRtl($code) ? 'RTL' : 'LTR';
            
            $babelobj->babelized = 0;
            return $babelobj;
    }
}

