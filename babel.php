<?php
namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;
use RocketTheme\Toolbox\Event\Event;
use Swift_RfcComplianceException;
use Grav\Plugin\Babel\Babel;
use SQLite3;
use Grav\Common\Filesystem\Folder;
use ReflectionProperty;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use Grav\Common\File\CompiledYamlFile;

class BabelPlugin extends Plugin
{
    /**
     * @var babel
     */
    protected $babel;
    protected $route = 'babel';
    protected $sqlite;
    protected $admin_route;
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized'      => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ],
            //'onFormProcessed' => ['onFormProcessed', 0],
            //'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ];
    }

    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *is
     * @return ClassLoader
     */
    public function autoload()
    {
        return require __DIR__ . '/vendor/autoload.php';
    }
    
    /**
     * Initialize emailing.
     */
    public function onPluginsInitialized()
    {
        
        if ($this->isAdmin()) {        
            
            $this->babel = new Babel();
            $this->grav['babel'] = $this->babel;
            $route = $this->config->get('plugins.admin.route');
            $base = '/' . trim($route, '/');
            $this->admin_route = $this->grav['base_url'] . $base;
            
            
            $this->enable([
                'onAdminTaskExecute' => ['onAdminTaskExecute', 0],                
                'onAdminMenu'                                => ['onAdminMenu', -100],  
                'onTwigTemplatePaths'                        => ['onTwigAdminTemplatePaths', 1000],
                'onTwigSiteVariables'        => ['onAdminTwigSiteVariables', 1000],
                'onDataTypeExcludeFromDataManagerPluginHook' => ['onDataTypeExcludeFromDataManagerPluginHook', 0],
            ]);
        }
        
        $this->enable([
            'onThemeInitialized' => ['onBabelLoadLanguages', 0],                
        ]);
        
                
        
    }
    
    /**
     * Set all twig variables for generating output.
     */
    public function onAdminTwigSiteVariables()
    {
        $twig = $this->grav['twig'];
        list($status, $msg) = $this->getIndexCount();
        
        if ($status === false) {
            $message = '<i class="fa fa-binoculars"></i> <a href="/'. trim($this->admin_route, '/') . '/plugins/babel">Babel must be indexed before it will function properly.</a>';
            $this->grav['admin']->addTempMessage($message, 'error');
            $twig->twig_vars['babel_index_status'] = ['status' => $status, 'msg' => $msg];
            $this->grav['assets']->addJs('plugin://babel/assets/admin/babel.js');
            
            //$this->grav['babel']->createIndex();
        } else {
        
            $twig->twig_vars['babel_index_status'] = ['status' => $status, 'msg' => $msg];
            $this->grav['assets']->addCss('plugin://babel/bower_components/bootstrap/dist/css/bootstrap.css');
            $this->grav['assets']->addCss('plugin://babel/assets/admin/babel.css');
            $this->grav['assets']->addCss('plugin://babel/bower_components/datatables.net-bs/css/dataTables.bootstrap.css');
            $this->grav['assets']->addCss('plugin://babel/bower_components/datatables.net-responsive-bs/css/responsive.bootstrap.css');
            
            $this->grav['assets']->addJs('plugin://babel/assets/admin/babel.js');
            
            $this->grav['assets']->addJs('plugin://babel/bower_components/datatables.net/js/jquery.dataTables.js');
            $this->grav['assets']->addJs('plugin://babel/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js');
            $this->grav['assets']->addJs('plugin://babel/bower_components/datatables.net-responsive/js/dataTables.responsive.min.js');
            $uri = $this->grav['uri'];
            if (strpos($uri->path(), $this->config->get('plugins.admin.route') . '/' . $this->route) === false) {
                //return;
            }
            $domain = $uri->param('domain');
            $twig->twig_vars['current_domain'] = $domain;
            $twig->twig_vars['babelstats'] = $this->babel->getBabelStats($domain);
            
            $twig->twig_vars['domains'] = $this->babel->getBabelDomains();
            /*
            $translations = Grav::instance()['languages'];

            $codes = Grav::instance()['config']->get('plugins.babel.translation_sets', ['en']);
            $babeldefinitions = [];
            foreach($codes as $code => $langdef) {
                $babels = $translations->get($langdef);
                if (is_array($babels) && count($babels)) {
                    $this->runBabelDefs($babeldefinitions, $babels, $langdef, '');
                }
            }
             * 
             */
        }
    }   
    /*
    private function runBabelDefs(&$babeldefinitions, $babels, $code, $route, $level = 0) {
        
        foreach($babels as $key => $babel) {
            if (!is_array($babel) && !in_array($key, $babeldefinitions)) {
                $id = $route . '.' . $key;
                $babeldefinitions[$id] = $id;
                //Grav::instance()['log']->info($id);
            } elseif (is_array($babel)) {
                if ($level == 0) {
                    $route = $key;                    
                } else {
                    $route = $route . '.' . $key;
                }
                $this->runBabelDefs($babeldefinitions, $babel, $code, $route, $level + 1);
                
            }
        }
    }

    private function createBabelDef($definition, $code) {
            $babelobj = new \stdClass();
            $id = $code . '.' . $definition;
            $babelobj->route = $id;
            $babelobj->domain = explode('.', $definition)[0];
            $babelobj->language = $code;
            $translation = $this->grav['language']->translate($definition, [$code]);
            
            $babelobj->definition = $translation;// . ' ' . $id . ' ' . str_replace('.', ' ', $id);
            return $babelobj;
            //$babelobjects[] = $babelobj;
    }

    
    private function runBabel(&$babelobjects, $babels, $code, $route) {
        foreach($babels as $key => $babel) {
            if (!is_array($babel) && count(explode('.', $route)) > 1) {
                $babelobj = new \stdClass();
                $id = $route . '.' . $key;
                $babelobj->route = $id;
                $babelobj->domain = explode('.', $route)[1];
                $babelobj->language = explode('.', $route)[0];
                $babelobj->definition = $babel . ' ' . $id . ' ' . str_replace('.', ' ', $id);
                $babelobjects[] = $babelobj;
            } elseif (is_array($babel)) {
                $this->runBabel($babelobjects, $babel, $code, $route . '.' . $key);
            }
        }
    }
    */
    
    /**
     * Wrapper to get the number of documents currently indexed
     *
     * @return array
     */
    protected function getIndexCount()
    {
        $status = true;
        try {
            $this->grav['babel']->selectIndex();
            $msg = $this->grav['babel']->babel->totalDocumentsInCollection() . ' definitions indexed';
            $msg = $this->grav['babel']->babel->totalDocumentsInCollection() . ' definitions indexed';
            
        } catch (IndexNotFoundException $e) {
            $status = false;
            $msg = "Index not created";
        }
        return [$status, $msg];
    }    
    
    /**
     * Add navigation item to the admin plugin
     */
    public function onAdminMenu()
    {
        $this->grav['twig']->plugins_hooked_nav['PLUGIN_BABEL.BABEL'] = [
            'route' => $this->route,
            'icon'  => 'fa-language'
        ];
    }
    
    /**
     * Add plugin templates path
     */
    public function onTwigAdminTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
    }
    
    /**
     * Add admin page template types.
     *
     * @param Event $event
     */
    public function onGetAdminPageTemplates(Event $event)
    {
        /** @var Types $types */
        $types = $event->types;
        $types->scanTemplates('plugins://babel/admin/templates');
    }
 
    /**
     * Handle the Reindex task from the admin
     *
     * @param Event $e
     */
    public function onAdminTaskExecute(Event $e)
    {
        if ($e['method'] == 'taskReindexBabel') {

            $controller = $e['controller'];

            header('Content-type: application/json');

            if (!$controller->authorizeTask('reindexBabel', ['admin.configuration', 'admin.super', 'admin.babel'])) {
                $json_response = [
                    'status'  => 'error',
                    'message' => '<i class="fa fa-warning"></i> Index not created',
                    'details' => 'Insufficient permissions to reindex the search engine database.'
                ];
                echo json_encode($json_response);
                exit;
            }

            // disable warnings
            error_reporting(1);
            
            // capture content
            ob_start();
            $this->grav['babel']->createIndex();
            ob_get_clean();

            list($status, $msg) = $this->getIndexCount();

            $json_response = [
                'status'  => $status ? 'success' : 'error',
                'message' => '<i class="fa fa-book"></i> ' . $msg
            ];
            echo json_encode($json_response);
            exit;
        }
        if ($e['method'] == 'taskGetSetBabel') {
            $controller = $e['controller'];

            header('Content-type: application/json');

            if (!$controller->authorizeTask('getSetBabel', ['admin.configuration', 'admin.super', 'admin.babel'])) {
                $json_response = [
                    'status'  => 'error',
                    'message' => '<i class="fa fa-warning"></i> Set not loaded',
                    'details' => 'Insufficient permissions to load translation set.'
                ];
                echo json_encode($json_response);
                exit;
            }
            
            // disable warnings
            error_reporting(1);
            $post = $_POST;
            
            $msg = $this->grav['babel']->getBabels($post);
            $json_response = $msg;
            
            echo json_encode($json_response);
            exit;
            
        }
        
        if ($e['method'] == 'taskSaveBabel') {
            $controller = $e['controller'];

            header('Content-type: application/json');

            if (!$controller->authorizeTask('saveBabel', ['admin.configuration', 'admin.super', 'admin.babel'])) {
                $json_response = [
                    'status'  => 'error',
                    'message' => '<i class="fa fa-warning"></i> Translation not saved',
                    'details' => 'Insufficient permissions to save the translation.'
                ];
                echo json_encode($json_response);
                exit;
            }
            
            // disable warnings
            error_reporting(1);
            $post = $_POST;
            
            $msg = $this->grav['babel']->saveBabel($post);
            $json_response = [
                'status'  => 'success',
                'message' => '<i class="fa fa-book"></i> Translation saved.'
            ];
            
            echo json_encode($json_response);
            exit;            
        }    
        
        if ($e['method'] == 'taskResetBabel') {
            $controller = $e['controller'];

            header('Content-type: application/json');

            if (!$controller->authorizeTask('resetBabel', ['admin.configuration', 'admin.super', 'admin.babel'])) {
                $json_response = [
                    'status'  => 'error',
                    'message' => '<i class="fa fa-warning"></i> Reset not executed',
                    'details' => 'Insufficient permissions to reset the definitions.'
                ];
                echo json_encode($json_response);
                exit;
            }
            
            // disable warnings
            error_reporting(1);
            $msg = $this->grav['babel']->resetBabel();
            $json_response = [
                'status'  => 'success',
                'message' => '<i class="fa fa-book"></i> Reset executed.'
            ];
            
            echo json_encode($json_response);
            exit;            
        }            

        if ($e['method'] == 'taskExportBabel') {
            $controller = $e['controller'];

            header('Content-type: application/json');

            if (!$controller->authorizeTask('exportBabel', ['admin.configuration', 'admin.super', 'admin.babel'])) {
                $json_response = [
                    'status'  => 'error',
                    'message' => '<i class="fa fa-warning"></i> Export not executed',
                    'details' => 'Insufficient permissions to export the definitions.'
                ];
                echo json_encode($json_response);
                exit;
            }
            
            // disable warnings
            error_reporting(1);
            
            $post = $_POST;            
            $msg = $this->grav['babel']->exportBabel($post);
            $json_response = [
                'status'  => 'success',
                'message' => '<i class="fa fa-book"></i> Export done.'
            ];
            
            echo json_encode($json_response);
            exit;            
        }            

        if ($e['method'] == 'taskMergeBabel') {
            $controller = $e['controller'];

            header('Content-type: application/json');

            if (!$controller->authorizeTask('mergeBabel', ['admin.configuration', 'admin.super', 'admin.babel'])) {
                $json_response = [
                    'status'  => 'error',
                    'message' => '<i class="fa fa-warning"></i> Merge not available',
                    'details' => 'Insufficient permissions to prepare merge of the definitions.'
                ];
                echo json_encode($json_response);
                exit;
            }
            
            // disable warnings
            error_reporting(1);
            
            $msg = $this->grav['babel']->mergeBabel();
            $json_response = [
                'status'  => 'success',
                'message' => '<i class="fa fa-book"></i> Merge done.'
            ];
            
            echo json_encode($json_response);
            exit;            
        }                   
    }

    /**
     * Load babel languages after Theme initialization to allow 
     * merging translations for theme language variables as well
     * 
     */
    public function onBabelLoadLanguages()
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];//$this->grav['locator'];
        if (Grav::instance()['config']->get('system.languages.translations', true)) {
            $languages_folder = $locator->findResource("user://data/babel/babelized");
            if (file_exists($languages_folder)) {
                $languages = [];
                $iterator = new \DirectoryIterator($languages_folder);

                /** @var \DirectoryIterator $directory */
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'yaml') {
                        continue;
                    }
                    $languages[$file->getBasename('.yaml')] = CompiledYamlFile::instance($file->getPathname())->content();
                }
                $this->grav['languages']->mergeRecursive($languages);
            }
        }
    }    
    
    /**
     * Exclude Orders from the Data Manager plugin
     */
    public function onDataTypeExcludeFromDataManagerPluginHook()
    {        
        $this->grav['admin']->dataTypesExcludedFromDataManagerPlugin[] = 'babel';
    }
    
    
}