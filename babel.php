<?php
namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Twig\Twig;
use RocketTheme\Toolbox\Event\Event;
use Swift_RfcComplianceException;
use Grav\Plugin\Babel\Babel;


class BabelPlugin extends Plugin
{
    /**
     * @var babel
     */
    protected $babel;
    protected $route = 'babel';

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            //'onFormProcessed' => ['onFormProcessed', 0],
            //'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ];
    }

    /**
     * Initialize emailing.
     */
    public function onPluginsInitialized()
    {
        
        require_once __DIR__ . '/vendor/autoload.php';

        $this->babel = new Babel();
        
        $uri = $this->grav['uri'];
        
        if ($this->isAdmin()) {        
            $this->enable([
                'onTwigTemplatePaths'                        => ['onTwigAdminTemplatePaths', 1000],
                'onAdminMenu'                                => ['onAdminMenu', -100],  
                'onTwigSiteVariables'        => ['onAdminTwigSiteVariables', 1000],
                //'onPageInitialized'                       => ['onAdminPageInitialized', 0],
                //'onAdminSave' => ['onAdminSave', 1000],	
            ]);
            if (strpos($uri->path(), $this->config->get('plugins.admin.route') . '/' . $this->route) === false) {
                return;
            }

        
        }
        /*
        if ($this->babel->enabled()) {
            $this->grav['Babel'] = $this->babel;
        }
         * 
         */
    }
    
    /**
     * Set all twig variables for generating output.
     */
    public function onAdminTwigSiteVariables()
    {
      if ($this->isAdmin()) {

	  
        $twig = $this->grav['twig'];
        $translations = Grav::instance()['languages'];
        $twig->twig_vars['translations'] = $translations->toArray();        
        //dump($translations->toArray());
      }
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
 
    
}