<?php
namespace Grav\Plugin\Babel;

use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\Yaml\Yaml;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use Grav\Plugin\Babel\BabelSearch;
use SQLite3;
use Grav\Common\File\CompiledYamlFile;
use Grav\Plugin\Babel\BabelConnector;
use \Grav\Common\Twig\TwigExtension;

class Babel
{
    public $babel;
    protected $options;
    protected $bool_characters = ['-', '(', ')', 'or'];
    protected $index = 'babel.index';
    protected $babelizations = [];
    protected $theme_variables = [];
    protected $export_path = 'user://data/babel';
    protected $babelConnector;
    
    public static $codes = [
        'af'         => [ 'name' => 'Afrikaans',                 'nativeName' => 'Afrikaans' ],
        'ak'         => [ 'name' => 'Akan',                      'nativeName' => 'Akan' ], // unverified native name
        'ast'        => [ 'name' => 'Asturian',                  'nativeName' => 'Asturianu' ],
        'ar'         => [ 'name' => 'Arabic',                    'nativeName' => 'عربي', 'orientation' => 'rtl'],
        'as'         => [ 'name' => 'Assamese',                  'nativeName' => 'অসমীয়া' ],
        'be'         => [ 'name' => 'Belarusian',                'nativeName' => 'Беларуская' ],
        'bg'         => [ 'name' => 'Bulgarian',                 'nativeName' => 'Български' ],
        'bn'         => [ 'name' => 'Bengali',                   'nativeName' => 'বাংলা' ],
        'bn-BD'      => [ 'name' => 'Bengali (Bangladesh)',      'nativeName' => 'বাংলা (বাংলাদেশ)' ],
        'bn-IN'      => [ 'name' => 'Bengali (India)',           'nativeName' => 'বাংলা (ভারত)' ],
        'br'         => [ 'name' => 'Breton',                    'nativeName' => 'Brezhoneg' ],
        'bs'         => [ 'name' => 'Bosnian',                   'nativeName' => 'Bosanski' ],
        'ca'         => [ 'name' => 'Catalan',                   'nativeName' => 'Català' ],
        'ca-valencia'=> [ 'name' => 'Catalan (Valencian)',       'nativeName' => 'Català (valencià)' ], // not iso-639-1. a=l10n-drivers
        'cs'         => [ 'name' => 'Czech',                     'nativeName' => 'Čeština' ],
        'cy'         => [ 'name' => 'Welsh',                     'nativeName' => 'Cymraeg' ],
        'da'         => [ 'name' => 'Danish',                    'nativeName' => 'Dansk' ],
        'de'         => [ 'name' => 'German',                    'nativeName' => 'Deutsch' ],
        'de-AT'      => [ 'name' => 'German (Austria)',          'nativeName' => 'Deutsch (Österreich)' ],
        'de-CH'      => [ 'name' => 'German (Switzerland)',      'nativeName' => 'Deutsch (Schweiz)' ],
        'de-DE'      => [ 'name' => 'German (Germany)',          'nativeName' => 'Deutsch (Deutschland)' ],
        'dsb'        => [ 'name' => 'Lower Sorbian',             'nativeName' => 'Dolnoserbšćina' ], // iso-639-2
        'el'         => [ 'name' => 'Greek',                     'nativeName' => 'Ελληνικά' ],
        'en'         => [ 'name' => 'English',                   'nativeName' => 'English' ],
        'en-AU'      => [ 'name' => 'English (Australian)',      'nativeName' => 'English (Australian)' ],
        'en-CA'      => [ 'name' => 'English (Canadian)',        'nativeName' => 'English (Canadian)' ],
        'en-GB'      => [ 'name' => 'English (British)',         'nativeName' => 'English (British)' ],
        'en-NZ'      => [ 'name' => 'English (New Zealand)',     'nativeName' => 'English (New Zealand)' ],
        'en-US'      => [ 'name' => 'English (US)',              'nativeName' => 'English (US)' ],
        'en-ZA'      => [ 'name' => 'English (South African)',   'nativeName' => 'English (South African)' ],
        'eo'         => [ 'name' => 'Esperanto',                 'nativeName' => 'Esperanto' ],
        'es'         => [ 'name' => 'Spanish',                   'nativeName' => 'Español' ],
        'es-AR'      => [ 'name' => 'Spanish (Argentina)',       'nativeName' => 'Español (de Argentina)' ],
        'es-CL'      => [ 'name' => 'Spanish (Chile)',           'nativeName' => 'Español (de Chile)' ],
        'es-ES'      => [ 'name' => 'Spanish (Spain)',           'nativeName' => 'Español (de España)' ],
        'es-MX'      => [ 'name' => 'Spanish (Mexico)',          'nativeName' => 'Español (de México)' ],
        'et'         => [ 'name' => 'Estonian',                  'nativeName' => 'Eesti keel' ],
        'eu'         => [ 'name' => 'Basque',                    'nativeName' => 'Euskara' ],
        'fa'         => [ 'name' => 'Persian',                   'nativeName' => 'فارسی' , 'orientation' => 'rtl' ],
        'fi'         => [ 'name' => 'Finnish',                   'nativeName' => 'Suomi' ],
        'fj-FJ'      => [ 'name' => 'Fijian',                    'nativeName' => 'Vosa vaka-Viti' ],
        'fr'         => [ 'name' => 'French',                    'nativeName' => 'Français' ],
        'fr-CA'      => [ 'name' => 'French (Canada)',           'nativeName' => 'Français (Canada)' ],
        'fr-FR'      => [ 'name' => 'French (France)',           'nativeName' => 'Français (France)' ],
        'fur'        => [ 'name' => 'Friulian',                  'nativeName' => 'Furlan' ],
        'fur-IT'     => [ 'name' => 'Friulian',                  'nativeName' => 'Furlan' ],
        'fy'         => [ 'name' => 'Frisian',                   'nativeName' => 'Frysk' ],
        'fy-NL'      => [ 'name' => 'Frisian',                   'nativeName' => 'Frysk' ],
        'ga'         => [ 'name' => 'Irish',                     'nativeName' => 'Gaeilge' ],
        'ga-IE'      => [ 'name' => 'Irish (Ireland)',           'nativeName' => 'Gaeilge (Éire)' ],
        'gd'         => [ 'name' => 'Gaelic (Scotland)',         'nativeName' => 'Gàidhlig' ],
        'gl'         => [ 'name' => 'Galician',                  'nativeName' => 'Galego' ],
        'gu'         => [ 'name' => 'Gujarati',                  'nativeName' => 'ગુજરાતી' ],
        'gu-IN'      => [ 'name' => 'Gujarati',                  'nativeName' => 'ગુજરાતી' ],
        'he'         => [ 'name' => 'Hebrew',                    'nativeName' => 'עברית', 'orientation' => 'rtl' ],
        'hi'         => [ 'name' => 'Hindi',                     'nativeName' => 'हिन्दी' ],
        'hi-IN'      => [ 'name' => 'Hindi (India)',             'nativeName' => 'हिन्दी (भारत)' ],
        'hr'         => [ 'name' => 'Croatian',                  'nativeName' => 'Hrvatski' ],
        'hsb'        => [ 'name' => 'Upper Sorbian',             'nativeName' => 'Hornjoserbsce' ],
        'hu'         => [ 'name' => 'Hungarian',                 'nativeName' => 'Magyar' ],
        'hy'         => [ 'name' => 'Armenian',                  'nativeName' => 'Հայերեն' ],
        'hy-AM'      => [ 'name' => 'Armenian',                  'nativeName' => 'Հայերեն' ],
        'id'         => [ 'name' => 'Indonesian',                'nativeName' => 'Bahasa Indonesia' ],
        'is'         => [ 'name' => 'Icelandic',                 'nativeName' => 'íslenska' ],
        'it'         => [ 'name' => 'Italian',                   'nativeName' => 'Italiano' ],
        'ja'         => [ 'name' => 'Japanese',                  'nativeName' => '日本語' ],
        'ja-JP'      => [ 'name' => 'Japanese',                  'nativeName' => '日本語' ], // not iso-639-1
        'ka'         => [ 'name' => 'Georgian',                  'nativeName' => 'ქართული' ],
        'kk'         => [ 'name' => 'Kazakh',                    'nativeName' => 'Қазақ' ],
        'kn'         => [ 'name' => 'Kannada',                   'nativeName' => 'ಕನ್ನಡ' ],
        'ko'         => [ 'name' => 'Korean',                    'nativeName' => '한국어' ],
        'ku'         => [ 'name' => 'Kurdish',                   'nativeName' => 'Kurdî' ],
        'la'         => [ 'name' => 'Latin',                     'nativeName' => 'Latina' ],
        'lb'         => [ 'name' => 'Luxembourgish',             'nativeName' => 'Lëtzebuergesch' ],
        'lg'         => [ 'name' => 'Luganda',                   'nativeName' => 'Luganda' ],
        'lt'         => [ 'name' => 'Lithuanian',                'nativeName' => 'Lietuvių kalba' ],
        'lv'         => [ 'name' => 'Latvian',                   'nativeName' => 'Latviešu' ],
        'mai'        => [ 'name' => 'Maithili',                  'nativeName' => 'मैथिली মৈথিলী' ],
        'mg'         => [ 'name' => 'Malagasy',                  'nativeName' => 'Malagasy' ],
        'mi'         => [ 'name' => 'Maori (Aotearoa)',          'nativeName' => 'Māori (Aotearoa)' ],
        'mk'         => [ 'name' => 'Macedonian',                'nativeName' => 'Македонски' ],
        'ml'         => [ 'name' => 'Malayalam',                 'nativeName' => 'മലയാളം' ],
        'mn'         => [ 'name' => 'Mongolian',                 'nativeName' => 'Монгол' ],
        'mr'         => [ 'name' => 'Marathi',                   'nativeName' => 'मराठी' ],
        'no'         => [ 'name' => 'Norwegian',                 'nativeName' => 'Norsk' ],
        'nb'         => [ 'name' => 'Norwegian',                 'nativeName' => 'Norsk' ],
        'nb-NO'      => [ 'name' => 'Norwegian (Bokmål)',        'nativeName' => 'Norsk bokmål' ],
        'ne-NP'      => [ 'name' => 'Nepali',                    'nativeName' => 'नेपाली' ],
        'nn-NO'      => [ 'name' => 'Norwegian (Nynorsk)',       'nativeName' => 'Norsk nynorsk' ],
        'nl'         => [ 'name' => 'Dutch',                     'nativeName' => 'Nederlands' ],
        'nr'         => [ 'name' => 'Ndebele, South',            'nativeName' => 'IsiNdebele' ],
        'nso'        => [ 'name' => 'Northern Sotho',            'nativeName' => 'Sepedi' ],
        'oc'         => [ 'name' => 'Occitan (Lengadocian)',     'nativeName' => 'Occitan (lengadocian)' ],
        'or'         => [ 'name' => 'Oriya',                     'nativeName' => 'ଓଡ଼ିଆ' ],
        'pa'         => [ 'name' => 'Punjabi',                   'nativeName' => 'ਪੰਜਾਬੀ' ],
        'pa-IN'      => [ 'name' => 'Punjabi',                   'nativeName' => 'ਪੰਜਾਬੀ' ],
        'pl'         => [ 'name' => 'Polish',                    'nativeName' => 'Polski' ],
        'pt'         => [ 'name' => 'Portuguese',                'nativeName' => 'Português' ],
        'pt-BR'      => [ 'name' => 'Portuguese (Brazilian)',    'nativeName' => 'Português (do Brasil)' ],
        'pt-PT'      => [ 'name' => 'Portuguese (Portugal)',     'nativeName' => 'Português (Europeu)' ],
        'ro'         => [ 'name' => 'Romanian',                  'nativeName' => 'Română' ],
        'rm'         => [ 'name' => 'Romansh',                   'nativeName' => 'Rumantsch' ],
        'ru'         => [ 'name' => 'Russian',                   'nativeName' => 'Русский' ],
        'rw'         => [ 'name' => 'Kinyarwanda',               'nativeName' => 'Ikinyarwanda' ],
        'si'         => [ 'name' => 'Sinhala',                   'nativeName' => 'සිංහල' ],
        'sk'         => [ 'name' => 'Slovak',                    'nativeName' => 'Slovenčina' ],
        'sl'         => [ 'name' => 'Slovenian',                 'nativeName' => 'Slovensko' ],
        'son'        => [ 'name' => 'Songhai',                   'nativeName' => 'Soŋay' ],
        'sq'         => [ 'name' => 'Albanian',                  'nativeName' => 'Shqip' ],
        'sr'         => [ 'name' => 'Serbian',                   'nativeName' => 'Српски' ],
        'sr-Latn'    => [ 'name' => 'Serbian',                   'nativeName' => 'Srpski' ], // follows RFC 4646
        'ss'         => [ 'name' => 'Siswati',                   'nativeName' => 'siSwati' ],
        'st'         => [ 'name' => 'Southern Sotho',            'nativeName' => 'Sesotho' ],
        'sv'         => [ 'name' => 'Swedish',                   'nativeName' => 'Svenska' ],
        'sv-SE'      => [ 'name' => 'Swedish',                   'nativeName' => 'Svenska' ],
        'ta'         => [ 'name' => 'Tamil',                     'nativeName' => 'தமிழ்' ],
        'ta-IN'      => [ 'name' => 'Tamil (India)',             'nativeName' => 'தமிழ் (இந்தியா)' ],
        'ta-LK'      => [ 'name' => 'Tamil (Sri Lanka)',         'nativeName' => 'தமிழ் (இலங்கை)' ],
        'te'         => [ 'name' => 'Telugu',                    'nativeName' => 'తెలుగు' ],
        'th'         => [ 'name' => 'Thai',                      'nativeName' => 'ไทย' ],
        'tlh'        => [ 'name' => 'Klingon',                   'nativeName' => 'Klingon' ],
        'tn'         => [ 'name' => 'Tswana',                    'nativeName' => 'Setswana' ],
        'tr'         => [ 'name' => 'Turkish',                   'nativeName' => 'Türkçe' ],
        'ts'         => [ 'name' => 'Tsonga',                    'nativeName' => 'Xitsonga' ],
        'tt'         => [ 'name' => 'Tatar',                     'nativeName' => 'Tatarça' ],
        'tt-RU'      => [ 'name' => 'Tatar',                     'nativeName' => 'Tatarça' ],
        'uk'         => [ 'name' => 'Ukrainian',                 'nativeName' => 'Українська' ],
        'ur'         => [ 'name' => 'Urdu',                      'nativeName' => 'اُردو', 'orientation' => 'rtl'  ],
        've'         => [ 'name' => 'Venda',                     'nativeName' => 'Tshivenḓa' ],
        'vi'         => [ 'name' => 'Vietnamese',                'nativeName' => 'Tiếng Việt' ],
        'wo'         => [ 'name' => 'Wolof',                     'nativeName' => 'Wolof' ],
        'xh'         => [ 'name' => 'Xhosa',                     'nativeName' => 'isiXhosa' ],
        'zh'         => [ 'name' => 'Chinese (Simplified)',      'nativeName' => '中文 (简体)' ],
        'zh-CN'      => [ 'name' => 'Chinese (Simplified)',      'nativeName' => '中文 (简体)' ],
        'zh-TW'      => [ 'name' => 'Chinese (Traditional)',     'nativeName' => '正體中文 (繁體)' ],
        'zu'         => [ 'name' => 'Zulu',                      'nativeName' => 'isiZulu' ]
    ];
    
    public static function getCodes() {
        return array_combine(array_keys(self::$codes), array_keys(self::$codes));
    }
    
    public static function isRtl($code) {
        if (isset(self::$codes[$code]['orientation']) && self::$codes[$code]['orientation'] == 'rtl') {
            return true;
        }
        return false;
    }

    public function __construct($options = [])
    {
        $search_type = Grav::instance()['config']->get('plugins.babel.search_type', 'auto');
        $stemmer = 'no';// Grav::instance()['config']->get('plugins.babel.stemmer', 'nostemmer');
        $limit = Grav::instance()['config']->get('plugins.babel.limit', 20);
        $snippet = Grav::instance()['config']->get('plugins.babel.snippet', 300);
        $data_path = Grav::instance()['locator']->findResource('user://data', true) . '/babel';

        if (!file_exists($data_path)) {
            mkdir($data_path);
        }

        
        $dbloc = $data_path . DS . $this->index;
        
        $defaults = [
            'json' => false,
            'search_type' => $search_type,
            'stemmer' => $stemmer,
            'limit' => $limit,
            'as_you_type' => true,
            'snippet' => $snippet,
            'phrases' => true,
        ];

        $this->options = array_merge($defaults, $options);
        $this->babel = new BabelSearch();
        $this->babel->loadConfig([
            "storage"   => $data_path,
            "driver"    => 'sqlite',
        ]);
        
        $this->babelConnector = new BabelConnector();
    }

    public function trackBabelizedVariables()
    {
        $locator = Grav::instance()['locator'];
        $languages_folder = $locator->findResource("user://data/babel/babelized");
        if (file_exists($languages_folder)) {
            $iterator = new \DirectoryIterator($languages_folder);
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'yaml') {
                    continue;
                }
                $babels = CompiledYamlFile::instance($file->getPathname())->content();
                $babeldefinitions = [];
                $code = pathinfo($file->getFilename())['filename'];
                $this->babelConnector->runBabelDefs($babeldefinitions, $babels);
                $this->babelizations[$code] = $babeldefinitions;
            }
        }
    }    
    
    public function trackThemeVariables()
    {
        $locator = Grav::instance()['locator'];
        $language_file = $locator->findResource("theme://languages" . YAML_EXT);
        $codes = Grav::instance()['config']->get('plugins.babel.translation_sets', ['en']);
        foreach($codes as $code => $langdef) {
            $this->theme_variables[$langdef] = [];
        }
        if ($language_file) {
            $themedefs = CompiledYamlFile::instance($language_file)->content();
            foreach($codes as $code => $langdef) {
                if (isset($themedefs[$langdef])) {
                    $babels = $themedefs[$langdef];
                    if (is_array($babels) && count($babels)) {
                        $babeldefinitions = [];
                        $this->babelConnector->runBabelDefs($babeldefinitions, $babels);
                        $this->theme_variables = $babeldefinitions;
                    }
                }
            }
        }
        $languages_folder = $locator->findResource("theme://languages/");
        if (file_exists($languages_folder)) {
            $languages = [];
            $iterator = new \DirectoryIterator($languages_folder);
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'yaml') {
                    continue;
                }
                $babels = CompiledYamlFile::instance($file->getPathname())->content();
                $babeldefinitions = [];
                $code = pathinfo($file->getFilename())['filename'];
                $this->babelConnector->runBabelDefs($babeldefinitions, $babels);
                $this->theme_variables = array_merge_recursive($this->theme_variables, $babeldefinitions);
            }
        }
    }     
    
    public function search($query) {
        $uri = Grav::instance()['uri'];
        $type = $uri->query('search_type');
        $this->babel->selectIndex($this->index);
        $this->babel->asYouType = $this->options['as_you_type'];

        if (isset($this->options['fuzzy']) && $this->options['fuzzy']) {
            $this->babel->fuzziness = true;
        }

        $limit = intval($this->options['limit']);
        $type = isset($type) ? $type : $this->options['search_type'];

        $multiword = null;
        if (isset($this->options['phrases']) && $this->options['phrases']) {
            if (strlen($query) > 2) {
                if ($query[0] === "\"" && $query[strlen($query) - 1] === "\"") {
                    $multiword = substr($query, 1, strlen($query) - 2);
                    $type = 'basic';
                    $query = $multiword;
                }
            }
        }


        switch ($type) {
            case 'basic':
                $results = $this->babel->search($query, $limit, $multiword);
                break;
            case 'boolean':
                $results = $this->babel->searchBoolean($query, $limit);
                break;
            case 'default':
            case 'auto':
            default:
                $guess = 'search';
                foreach ($this->bool_characters as $char) {
                    if (strpos($query, $char) !== false) {
                        $guess = 'searchBoolean';
                        break;
                    }
                }

                $results = $this->babel->$guess($query, $limit);
        }

        return $this->processResults($results, $query);
    }

    protected function processResults($res, $query)
    {
        $counter = 0;
        $data = new \stdClass();
        $data->number_of_hits = isset($res['hits']) ? $res['hits'] : 0;
        $data->execution_time = $res['execution_time'];
        $pages = Grav::instance()['pages'];

        foreach ($res['ids'] as $path) {

            if ($counter++ > $this->options['limit']) {
                break;
            }

            $page = $pages->dispatch($path);

            if ($page) {
                Grav::instance()->fireEvent('onBabelQuery', new Event(['page' => $page, 'query' => $query, 'options' => $this->options, 'fields' => $data, 'gbabel' => $this]));
            }
        }

        if ($this->options['json']) {
            return json_encode($data, JSON_PRETTY_PRINT);
        } else {
            return $data;
        }
    }

    public static function getCleanBabel($content)
    {
        $content = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", strip_tags($content)));

        return $content;
    }
    
    
    public function createIndex()
    {
        $this->babel->setDatabaseHandle(new BabelConnector);
        $indexer = $this->babel->createIndex($this->index);

        // Set the stemmer language if set
        if ($this->options['stemmer'] != 'default') {
            $indexer->setLanguage($this->options['stemmer']);
        }
        
        $indexer->run();
    }

    public function selectIndex()
    {
        $this->babel->selectIndex($this->index);
    }

    public function deleteIndex($obj)
    {
        if ($obj instanceof Page) {
            $page = $obj;
        } else {
            return;
        }

        $this->babel->setDatabaseHandle(new BabelConnector);

        try {
            $this->babel->selectIndex($this->index);
        } catch (IndexNotFoundException $e) {
            return;
        }

        $indexer = $this->babel->getIndex();

        // Delete existing if it exists
        $indexer->delete($page->route());
    }

    public function updateIndex($obj)
    {
        if ($obj instanceof Page) {
            $page = $obj;
        } else {
            return;
        }

        $this->babel->setDatabaseHandle(new BabelConnector);

        try {
            $this->babel->selectIndex($this->index);
        } catch (IndexNotFoundException $e) {
            return;
        }
        
        $indexer = $this->babel->getIndex();

        
        // Delete existing if it exists
        $indexer->delete($page->route());

        
        
        $filter = $config = Grav::instance()['config']->get('plugins.babel.filter');
        if ($filter && array_key_exists('items', $filter)) {

            if (is_string($filter['items'])) {
                $filter['items'] = Yaml::parse($filter['items']);
            }

            $apage = new Page;
            /** @var Collection $collection */
            $collection = $apage->collection($filter, false);

            if (array_key_exists($page->path(), $collection->toArray())) {
                
                $fields = BabelSearch::indexPageData($page);
                $document = (array) $fields;

                // Insert document
                $indexer->insert($document);
            }
        }
    }

    public function indexBabelData($babel)
    {
        $fields = new \stdClass();
        $fields->id = $babel->id;
        $fields->route = $babel->route;
        $fields->domain = $babel->domain;
        $fields->language = $babel->language;
        $fields->content = $this->getCleanBabel($babel->definition);        
        $fields->original = $babel->definition;
        $fields->translated = $babel->definition;
        $fields->status = $babel->status;
        $fields->translations = $babel->translations;
        $fields->rtl = $babel->rtl;
        // Track Babel edits here
        if (count($this->babelizations) && isset($this->babelizations[$babel->language]) && isset($this->babelizations[$babel->language][$babel->route])) {
            $fields->babelized = 1;
        } else {
            $fields->babelized = $babel->babelized;
        }

        if (count($this->theme_variables) && isset($this->theme_variables[$babel->route])) {
            $fields->istheme = 1;
        } else {
            $fields->istheme = 0;
        }
        
        
        
        //$fileds->translations = $babel->translations;
        //Grav::instance()->fireEvent('onBabelIndex', new Event(['page' => $page, 'fields' => $fields]));
        return $fields;
    }
    
    public function getBabelStats($domain)
    {
        return $this->babel->getBabelStats(Grav::instance()['config']->get('plugins.babel.translation_sets', ['en']), $domain);
    }

    public function getBabelDomains()
    {
        
        return $this->babel->getBabelDomains();
    }
    
    public function getBabels($post)
    {
        if ($post['domain'] == '*b') {
            $test = $this->babel->getBabels($post);
            Grav::instance()['log']->info($test);
            return $test;
        }
        return $this->babel->getBabels($post);
    }

    public function saveBabel($post)
    {
        return $this->babel->saveBabel($post);
    }

    public function resetBabel()
    {
        return $this->babel->resetBabel();
    }

    public function exportBabel($post)
    {
        return $this->babel->exportBabel($post);
    }

    public function mergeBabel()
    {
        return $this->babel->mergeBabel();
    }    
    
    public function getDomainFiles($domains) {
        $domainfiles = [];        
        $twig_extension = new TwigExtension();        
        $zip_path = Grav::instance()['locator']->findResource($this->export_path, true) . '/zips';
        foreach($domains as $domain) {
            $zipFile = $zip_path . DS . $domain . '_languages.zip';
            if (file_exists($zipFile)) {
                $domainfiles[$domain] = $twig_extension->urlFunc($this->export_path . '/zips/' . $domain . '_languages.zip', true);
            }
        }
        return $domainfiles;
    }
   
}
