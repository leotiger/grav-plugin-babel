<?php

namespace Grav\Plugin\Babel;

use PDO;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Support\Expression;
use TeamTNT\TNTSearch\Support\Highlighter;
use TeamTNT\TNTSearch\Support\Tokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;
use TeamTNT\TNTSearch\TNTSearch;
use Grav\Plugin\Babel\BabelIndexer;
use Symfony\Component\Yaml\Yaml;
use RocketTheme\Toolbox\File\File;
use Grav\Common\Data\Data;
use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;



class BabelSearch extends TNTSearch
{
    public $config;
    public $asYouType            = false;
    public $maxDocs              = 500;
    public $tokenizer            = null;
    public $index                = null;
    public $stemmer              = null;
    public $fuzziness            = false;
    public $fuzzy_prefix_length  = 2;
    public $fuzzy_max_expansions = 50;
    public $fuzzy_distance       = 2;
    protected $dbh               = null;

    /**
     * @param array $config
     *
     * @see https://github.com/teamtnt/tntsearch#examples
     */
    public function loadConfig(array $config)
    {
        $this->config            = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/').'/';
    }

    public function __construct()
    {
        $this->tokenizer = new Tokenizer;
    }

    /**
     * @param PDO $dbh
     */
    public function setDatabaseHandle(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * @param TokenizerInterface $tokenizer
     */
    public function setTokenizer(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * @param string $indexName
     * @param boolean $disableOutput
     *
     * @return TNTIndexer
     */
    public function createIndex($indexName, $disableOutput = false)
    {
        $indexer = new BabelIndexer;
        $indexer->loadConfig($this->config);
        $indexer->disableOutput = $disableOutput;

        if ($this->dbh) {
            $indexer->setDatabaseHandle($this->dbh);
        }
        return $indexer->createIndex($indexName);
    }

    /**
     * @param string $indexName
     *
     * @throws IndexNotFoundException
     */
    public function selectIndex($indexName)
    {
        $pathToIndex = $this->config['storage'].$indexName;
        if (!file_exists($pathToIndex)) {
            //return;
            throw new IndexNotFoundException("Index {$pathToIndex} does not exist", 1);
        }
        try {
            $this->index = new PDO('sqlite:'.$pathToIndex);
            $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->setStemmer();
        } catch(\Whoops\Exception\ErrorException $e) {
            throw new IndexNotFoundException("Index {$pathToIndex} not prepared. Please reload.", 1);
        }
    }

    /**
     * @param string $phrase
     * @param int    $numOfResults
     *
     * @return array
     */
    public function search($phrase, $numOfResults = 100)
    {
        $startTimer = microtime(true);
        $keywords   = $this->breakIntoTokens($phrase);
        $keywords   = new Collection($keywords);

        $keywords = $keywords->map(function ($keyword) {
            return $this->stemmer->stem($keyword);
        });

        $tfWeight  = 1;
        $dlWeight  = 0.5;
        $docScores = [];
        $count     = $this->totalDocumentsInCollection();

        foreach ($keywords as $index => $term) {
            $isLastKeyword = ($keywords->count() - 1) == $index;
            $df            = $this->totalMatchingDocuments($term, $isLastKeyword);
            foreach ($this->getAllDocumentsForKeyword($term, false, $isLastKeyword) as $document) {
                $docID = $document['doc_id'];
                $tf    = $document['hit_count'];
                $idf   = log($count / $df);
                $num   = ($tfWeight + 1) * $tf;
                $denom = $tfWeight
                     * ((1 - $dlWeight) + $dlWeight)
                     + $tf;
                $score             = $idf * ($num / $denom);
                $docScores[$docID] = isset($docScores[$docID]) ?
                $docScores[$docID] + $score : $score;
            }
        }

        arsort($docScores);

        $docs = new Collection($docScores);

        $counter   = 0;
        $totalHits = $docs->count();
        $docs      = $docs->map(function ($doc, $key) {
            return $key;
        })->filter(function ($item) use (&$counter, $numOfResults) {
            $counter++;
            if ($counter <= $numOfResults) {
                return true;
            }
            return false; // ?
        });
        $stopTimer = microtime(true);

        if ($this->isFileSystemIndex()) {
            return $this->filesystemMapIdsToPaths($docs)->toArray();
        }
        return [
            'ids'            => array_keys($docs->toArray()),
            'hits'           => $totalHits,
            'execution_time' => round($stopTimer - $startTimer, 7) * 1000 ." ms"
        ];
    }

    /**
     * @param string $phrase
     * @param int    $numOfResults
     *
     * @return array
     */
    public function searchBoolean($phrase, $numOfResults = 100)
    {
        $stack      = [];
        $startTimer = microtime(true);

        $expression = new Expression;
        $postfix    = $expression->toPostfix("|".$phrase);

        foreach ($postfix as $token) {
            if ($token == '&') {
                $left  = array_pop($stack);
                $right = array_pop($stack);
                if (is_string($left)) {
                    $left = $this->getAllDocumentsForKeyword($this->stemmer->stem($left), true)
                        ->pluck('doc_id');
                }
                if (is_string($right)) {
                    $right = $this->getAllDocumentsForKeyword($this->stemmer->stem($right), true)
                        ->pluck('doc_id');
                }
                if (is_null($left)) {
                    $left = [];
                }

                if (is_null($right)) {
                    $right = [];
                }
                $stack[] = array_values(array_intersect($left, $right));
            } else
            if ($token == '|') {
                $left  = array_pop($stack);
                $right = array_pop($stack);

                if (is_string($left)) {
                    $left = $this->getAllDocumentsForKeyword($this->stemmer->stem($left), true)
                        ->pluck('doc_id');
                }
                if (is_string($right)) {
                    $right = $this->getAllDocumentsForKeyword($this->stemmer->stem($right), true)
                        ->pluck('doc_id');
                }
                if (is_null($left)) {
                    $left = [];
                }

                if (is_null($right)) {
                    $right = [];
                }
                $stack[] = array_unique(array_merge($left, $right));
            } else
            if ($token == '~') {
                $left = array_pop($stack);
                if (is_string($left)) {
                    $left = $this->getAllDocumentsForWhereKeywordNot($this->stemmer->stem($left), true)
                        ->pluck('doc_id');
                }
                if (is_null($left)) {
                    $left = [];
                }
                $stack[] = $left;
            } else {
                $stack[] = $token;
            }
        }
        if (count($stack)) {
            $docs = new Collection($stack[0]);
        } else {
            $docs = new Collection;
        }

        $counter = 0;
        $docs    = $docs->filter(function ($item) use (&$counter, $numOfResults) {
            $counter++;
            if ($counter <= $numOfResults) {
                return $item;
            }
            return false; // ?
        });

        $stopTimer = microtime(true);

        if ($this->isFileSystemIndex()) {
            return $this->filesystemMapIdsToPaths($docs)->toArray();
        }

        return [
            'ids'            => $docs->toArray(),
            'hits'           => $docs->count(),
            'execution_time' => round($stopTimer - $startTimer, 7) * 1000 ." ms"
        ];
    }

    /**
     * @param      $keyword
     * @param bool $noLimit
     * @param bool $isLastKeyword
     *
     * @return Collection
     */
    public function getAllDocumentsForKeyword($keyword, $noLimit = false, $isLastKeyword = false)
    {
        $word = $this->getWordlistByKeyword($keyword, $isLastKeyword);
        if (!isset($word[0])) {
            return new Collection([]);
        }
        if ($this->fuzziness) {
            return $this->getAllDocumentsForFuzzyKeyword($word, $noLimit);
        }

        return $this->getAllDocumentsForStrictKeyword($word, $noLimit);
    }

    /**
     * @param      $keyword
     * @param bool $noLimit
     *
     * @return Collection
     */
    public function getAllDocumentsForWhereKeywordNot($keyword, $noLimit = false)
    {
        $word = $this->getWordlistByKeyword($keyword);
        if (!isset($word[0])) {
            return new Collection([]);
        }
        $query = "SELECT * FROM doclist WHERE doc_id NOT IN (SELECT doc_id FROM doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC LIMIT {$this->maxDocs}";
        if ($noLimit) {
            $query = "SELECT * FROM doclist WHERE doc_id NOT IN (SELECT doc_id FROM doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC";
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id']);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param      $keyword
     * @param bool $isLastWord
     *
     * @return int
     */
    public function totalMatchingDocuments($keyword, $isLastWord = false)
    {
        $occurance = $this->getWordlistByKeyword($keyword, $isLastWord);
        if (isset($occurance[0])) {
            return $occurance[0]['num_docs'];
        }

        return 0;
    }

    /**
     * @param      $keyword
     * @param bool $isLastWord
     *
     * @return array
     */
    public function getWordlistByKeyword($keyword, $isLastWord = false)
    {
        if ($this->fuzziness) {
            return $this->fuzzySearch($keyword);
        }

        $searchWordlist = "SELECT * FROM wordlist WHERE term like :keyword LIMIT 1";
        $stmtWord       = $this->index->prepare($searchWordlist);

        if ($this->asYouType && $isLastWord) {
            $searchWordlist = "SELECT * FROM wordlist WHERE term like :keyword ORDER BY length(term) ASC, num_hits DESC LIMIT 1";
            $stmtWord       = $this->index->prepare($searchWordlist);
            $stmtWord->bindValue(':keyword', mb_strtolower($keyword)."%");
        } else {
            $stmtWord->bindValue(':keyword', mb_strtolower($keyword));
        }
        $stmtWord->execute();
        $res = $stmtWord->fetchAll(PDO::FETCH_ASSOC);

        return $res;
    }

    /**
     * @param $keyword
     *
     * @return array
     */
    public function fuzzySearch($keyword)
    {
        $prefix         = substr($keyword, 0, $this->fuzzy_prefix_length);
        $searchWordlist = "SELECT * FROM wordlist WHERE term like :keyword ORDER BY num_hits DESC LIMIT {$this->fuzzy_max_expansions}";
        $stmtWord       = $this->index->prepare($searchWordlist);
        $stmtWord->bindValue(':keyword', mb_strtolower($prefix)."%");
        $stmtWord->execute();
        $matches = $stmtWord->fetchAll(PDO::FETCH_ASSOC);

        $resultSet = [];
        foreach ($matches as $match) {
            $distance = levenshtein($match['term'], $keyword);
            if ($distance <= $this->fuzzy_distance) {
                $match['distance'] = $distance;
                $resultSet[]       = $match;
            }
        }

        // Sort the data by distance, and than by num_hits
        $distance = [];
        $hits     = [];
        foreach ($resultSet as $key => $row) {
            $distance[$key] = $row['distance'];
            $hits[$key]     = $row['num_hits'];
        }
        array_multisort($distance, SORT_ASC, $hits, SORT_DESC, $resultSet);

        return $resultSet;
    }

    public function totalDocumentsInCollection()
    {
        return $this->getValueFromInfoTable('total_documents');
    }

    public function getStemmer()
    {
        return $this->stemmer;
    }

    public function setStemmer()
    {
        $stemmer = $this->getValueFromInfoTable('stemmer');
        if ($stemmer) {
            $this->stemmer = new $stemmer;
        } else {
            $this->stemmer = new PorterStemmer;
        }
    }

    /**
     * @return bool
     */
    public function isFileSystemIndex()
    {
        return $this->getValueFromInfoTable('driver') == 'filesystem';
    }

    public function getValueFromInfoTable($value)
    {
        $query = "SELECT * FROM info WHERE key = '$value'";
        $docs  = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    /**
     * @param array $languages
     * @param string $domain optional
     *
     * @return array $stats
     */
    public function getBabelStats($languages, $domain = "")
    {
        $stats = [];
        //$babelized = 0;
        if ($domain == '*b') {
            $domain = false;
            $babelized = 1;            
        }
        if ($domain == 'THEME_TRACKED') {
            $domain = false;
            $istheme = 1;            
        }
        
        foreach($languages as $language) {            
            $query = "SELECT COUNT(doc_id) FROM babellist WHERE " . (isset($istheme) ? 'istheme = 1 and ' : '') . (isset($babelized) ? 'babelized = 1 and ' : '') . "language = :language AND status = 0" . ($domain ? ' AND domain = :domain' : "");
            $stmtDoc = $this->index->prepare($query);
            $stmtDoc->bindValue(':language', $language);
            if ($domain) {
                $stmtDoc->bindValue(':domain', $domain);
            }
            $stmtDoc->execute();            
            $untranslated = $stmtDoc->fetchAll(PDO::FETCH_COLUMN);
            
            $query = "SELECT COUNT(doc_id) FROM babellist WHERE " . (isset($istheme) ? 'istheme = 1 and ' : '') . (isset($babelized) ? 'babelized = 1 and ' : '') . "language = :language AND status = 1" . ($domain ? ' AND domain = :domain' : "");
            $stmtDoc = $this->index->prepare($query);
            $stmtDoc->bindValue(':language', $language);
            if ($domain) {
                $stmtDoc->bindValue(':domain', $domain);
            }
            $stmtDoc->execute();            
            $translated = $stmtDoc->fetchAll(PDO::FETCH_COLUMN);
            
            $query = "SELECT COUNT(doc_id) FROM babellist WHERE " . (isset($istheme) ? 'istheme = 1 and ' : '') . (isset($babelized) ? 'babelized = 1 and ' : '') . "language = :language" . ($domain ? ' AND domain = :domain' : "");
            $stmtDoc = $this->index->prepare($query);
            $stmtDoc->bindValue(':language', $language);
            if ($domain) {
                $stmtDoc->bindValue(':domain', $domain);
            }
            $stmtDoc->execute();            
            $all = $stmtDoc->fetchAll(PDO::FETCH_COLUMN);
            
            $stats[$language] = ['all' => $all[0], 'untranslated' => $untranslated[0], 'translated' => $translated[0]];
        }
        return $stats;
    }
    
    /**
     * Get translation domains
     * 
     * @return array $domains
     */
    public function getBabelDomains()
    {
        $query = "SELECT DISTINCT domain FROM babellist ORDER BY domain";
        $stmtDoc = $this->index->prepare($query);
        $stmtDoc->execute();            
        return $stmtDoc->fetchAll(PDO::FETCH_COLUMN);
    }
        
    /**
     * Get babel definitions
     * @param array $post
     * @return array $babels
     */
    public function getBabels($post)
    {
        $domain = $post['domain'] == '*' || $post['domain'] == '*b' ? '' : $post['domain'];
        $babelized = $post['domain'] == '*b' ? 1 : 0;
        $lang = $post['lang'];
        $status = intval($post['status']);
        if ($domain == 'THEME_TRACKED') {
            $domain = false;
            $istheme = true;
        }
        
        
        if (is_null($this->index)) {
            $pathToIndex = $this->config['storage'] . 'babel.index';
            $this->index = new PDO('sqlite:'.$pathToIndex);
            $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        $query = "SELECT * FROM babellist WHERE " . (isset($istheme) ? 'istheme = 1 and ' : '') . ($babelized ? 'babelized = 1 and ' : '') . "language = :language" . ($status === 0 || $status === 1 ? ' AND status = :status' : '') . ($domain && $domain != 'babelized' ? ' AND domain = :domain' : "");
        $stmtDoc = $this->index->prepare($query);
        
        $stmtDoc->bindValue(':language', $lang);
        if ($domain) {
            $stmtDoc->bindValue(':domain', $domain);
        }
        if ($status === 0 || $status === 1) {
            $stmtDoc->bindValue(':status', $status);
        }
        $stmtDoc->execute();            
        return $stmtDoc->fetchAll(PDO::FETCH_SERIALIZE);
    }
    
    public function saveBabel($post) {  
        if (is_null($this->index)) {
            $pathToIndex = $this->config['storage'] . 'babel.index';
            $this->index = new PDO('sqlite:'.$pathToIndex);
            $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }        
        $query = "UPDATE babellist SET translated = :translation, babelized = 1, status = :status WHERE doc_id = :doc_id";
        $stmtDoc = $this->index->prepare($query);
        $stmtDoc->bindValue(':translation', $post['translation']);
        if ($post['translation']) {
            $stmtDoc->bindValue(':status', 1);
        } else {
            $stmtDoc->bindValue(':status', 0);
        }
        $stmtDoc->bindValue(':doc_id', $post['doc_id']);
        $stmtDoc->execute();
    }

    public function resetBabel() {  
        if (is_null($this->index)) {
            $pathToIndex = $this->config['storage'] . 'babel.index';
            $this->index = new PDO('sqlite:'.$pathToIndex);
            $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }        
        $query = "UPDATE babellist SET translated = original, babelized = 0, status = originalstatus WHERE babelized = 1";
        $stmtDoc = $this->index->prepare($query);
        $stmtDoc->execute();
        
        //$this->index->exec($stmtDoc->queryString);
    }
    
    public function exportBabel($post) {  
        if (is_null($this->index)) {
            $pathToIndex = $this->config['storage'] . 'babel.index';
            $this->index = new PDO('sqlite:'.$pathToIndex);
            $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        $lang = $post['lang'];
        $domain = $post['domain'];
        if ($lang && $domain) {
            $pathToExport = $this->config['storage'] . $post['domain'];
            if (!file_exists($pathToExport)) {
                mkdir($pathToExport);
            }

            if ($domain == 'THEME_TRACKED') {
                $query = "SELECT route, translated FROM babellist WHERE istheme = 1 and language = :language ORDER BY route";
            } else {
                $query = "SELECT route, translated FROM babellist WHERE language = :language AND domain = :domain ORDER BY route";                 
            }
            $stmtDoc = $this->index->prepare($query);

            $stmtDoc->bindValue(':language', $lang);
            if ($domain != 'THEME_TRACKED') {
                $stmtDoc->bindValue(':domain', $domain);
            }
            $stmtDoc->execute(); 
            
            $export = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
            
            $translations = [];
            foreach($export as $definition) {                                
                $route = str_replace('unclassified.', '', $definition['route']);
                $translations = array_merge_recursive($translations, $this->toYamlArray(explode('.', $route), $definition['translated']));
            }
            
            $yamlfile = CompiledYamlFile::instance($pathToExport . DS . $lang . '.yaml');            
            $yaml = new Data($translations);
            $yaml->file($yamlfile);
            $yaml->save();
        }
        $this->zipExport($this->config['storage'], $pathToExport, $domain);
    }
     
    private function zipExport($storage, $folder, $domain) {
        $zipDir = $storage . 'zips';
        if (!file_exists($zipDir)) {
            mkdir($zipDir);
        }
        $zipFile = $zipDir . DS . $domain . '_languages.zip';
        $zipArchive = new \ZipArchive();

        if (!$zipArchive->open($zipFile, \ZipArchive::CREATE)) {
            Grav::instance()['log']->info('Failed to create zip archive for domain ' . $domain . ' export.');
            return;
        }
        $globOptions = array('remove_all_path' => TRUE);
        $zipArchive->addGlob($storage . $domain . DS . '*' . YAML_EXT, GLOB_BRACE, $globOptions);
        if (!$zipArchive->status == \ZipArchive::ER_OK) {
            Grav::instance()['log']->info('addGlob failed...');
            return;
        }
        $zipArchive->close();    
    }
    
    
    public function mergeBabel() {  
        if (is_null($this->index)) {
            $pathToIndex = $this->config['storage'] . 'babel.index';
            $this->index = new PDO('sqlite:'.$pathToIndex);
            $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        $codes = Grav::instance()['config']->get('plugins.babel.translation_sets', ['en']);
        $data_path = Grav::instance()['locator']->findResource('user://data/babel', true) . '/babelized';

        if (!file_exists($data_path)) {
            mkdir($data_path);

        }
        
        
        foreach($codes as $code => $langdef) {
        
            //$pathToExport = $this->config['storage'] . $post['domain'];
            if (!file_exists($pathToExport)) {
                mkdir($pathToExport);
            }
            
            // We need to export all variables, otherwise changes done in previous Babel sessions get lost.
            $query = "SELECT route, translated FROM babellist WHERE babelized = 1 and status = 1 and language = :language ORDER BY route";
            $stmtDoc = $this->index->prepare($query);

            $stmtDoc->bindValue(':language', $langdef);
            $stmtDoc->execute(); 

            $export = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

            $translations = [];
            foreach($export as $definition) {     
                $route = str_replace('unclassified.', '', $definition['route']);
                $translations = array_merge_recursive($translations, $this->toYamlArray(explode('.', $route), $definition['translated']));
            }
            if (count($translations)) {
                $yamlfile = CompiledYamlFile::instance($data_path . DS . $langdef . '.yaml');            
                $yaml = new Data($translations);
                $yaml->file($yamlfile);
                $yaml->save();
            }
        }
    }    
    
    private function toYamlArray($keys, $value) {
        $var = array();   
        $index = array_shift($keys);
        if (!isset($keys[0])) {
            $var[$index] = $value;
        } else {   
            $var[$index] =  $this->toYamlArray($keys, $value);            
        }
        return $var;
    }
    
    public function filesystemMapIdsToPaths($docs)
    {
        $query = "SELECT * FROM filemap WHERE id in (".$docs->implode(', ').");";
        $res   = $this->index->query($query)->fetchAll(PDO::FETCH_ASSOC);

        return $docs->map(function ($key) use ($res) {
            $index = array_search($key, array_column($res, 'id'));
            return $res[$index];
        });
    }

    public function info($str)
    {
        echo $str."\n";
    }

    public function breakIntoTokens($text)
    {
        return $this->tokenizer->tokenize($text);
    }

    /**
     * @param        $text
     * @param        $needle
     * @param string $tag
     * @param array  $options
     *
     * @return string
     */
    public function highlight($text, $needle, $tag = 'em', $options = [])
    {
        $hl = new Highlighter;
        return $hl->highlight($text, $needle, $tag, $options);
    }

    public function snippet($words, $fulltext, $rellength = 300, $prevcount = 50, $indicator = '...')
    {
        $hl = new Highlighter;
        return $hl->extractRelevant($words, $fulltext, $rellength, $prevcount, $indicator);
    }

    /**
     * @return TNTIndexer
     */
    public function getIndex()
    {
        $indexer           = new BabelIndexer;
        $indexer->inMemory = false;
        $indexer->setIndex($this->index);
        $indexer->setStemmer($this->stemmer);
        return $indexer;
    }

    /**
     * @param $words
     * @param $noLimit
     *
     * @return Collection
     */
    private function getAllDocumentsForFuzzyKeyword($words, $noLimit)
    {
        $binding_params = implode(',', array_fill(0, count($words), '?'));
        $query = "SELECT * FROM doclist WHERE term_id in ($binding_params) ORDER BY hit_count DESC LIMIT {$this->maxDocs}";
        if ($noLimit) {
            $query = "SELECT * FROM doclist WHERE term_id in ($binding_params) ORDER BY hit_count DESC";
        }
        $stmtDoc = $this->index->prepare($query);

        $ids = null;
        foreach ($words as $word) {
            $ids[] = $word['id'];
        }
        $stmtDoc->execute($ids);
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param $word
     * @param $noLimit
     *
     * @return Collection
     */
    private function getAllDocumentsForStrictKeyword($word, $noLimit)
    {
        $query = "SELECT * FROM doclist WHERE term_id = :id ORDER BY hit_count DESC LIMIT {$this->maxDocs}";
        if ($noLimit) {
            $query = "SELECT * FROM doclist WHERE term_id = :id ORDER BY hit_count DESC";
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id']);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }
}
