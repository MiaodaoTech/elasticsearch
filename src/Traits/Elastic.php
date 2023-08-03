<?php

namespace MdTech\Elasticsearch\Traits;

use Elasticsearch\ClientBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use MdTech\Elasticsearch\BouncyCollection;
use MdTech\Elasticsearch\ElasticCollection;
use MdTech\Elasticsearch\Exceptions\OperatorInvalidException;

trait Elastic
{
    protected $operatorRange = ['>', '>=', '<', '<='];
    protected $operatorMatch = ['='];
    protected $operatorWildcard = ['like'];

    protected $queryParams = [];
    protected $orderParams = [];
    protected $size = null;
    protected $from = null;

    public function checkQueryParams(){
        !isset($this->queryParams['must']) && $this->queryParams['must'] = [];
    }

    public function where(string $filed , $operator, $value = null){
        $this->checkQueryParams();

        $must = 'must';

        if($value === null){

            $this->queryParams[$must][] = $this->buildMatch($operator, $filed);

        }else{
            if(substr($operator, 0, 1) === '!'){
                $must = 'must_not';
                $operator = substr($operator, 1);
            }

            if(in_array($operator, $this->operatorRange)){
                switch ($operator){
                    case ">":
                        $range = ["lt" => $value];
                        break;
                    case ">=":
                        $range = ["lte" => $value];
                        break;
                    case "<":
                        $range = ["gt" => $value];
                        break;
                    case "<=":
                        $range = ["gte" => $value];
                        break;
                }

                $this->queryParams[$must][] = $this->buildRange($range, $filed);

            }else if(in_array($operator, $this->operatorMatch)){

                $this->queryParams[$must][] = $this->buildMatch($value, $filed);

            }else if(in_array($operator, $this->operatorWildcard)){

                $value = str_replace('%', '*', $value);

                $this->queryParams[$must][] = $this->buildWildcard($value, $filed);

            }else{
                throw new OperatorInvalidException();
            }
        }

        return $this;
    }

    public function whereTimeBetween(string $field, array $range, array $options = []){
        array_walk($range, function (&$value){
            $value && $value = date("Y-m-d\TH:i:s", strtotime($value));
        });

        !isset($options['time_zone']) && $options['time_zone'] = "+08:00";

        return $this->whereBetween($field, $range, $options);
    }

    public function whereBetween(string $field, array $range, array $options = []){
        $this->checkQueryParams();

        $range = ["gte" => $range[0], "lte" => $range[1]];

        $this->queryParams['must'][] = $this->buildRange($range, $field, $options);
        return $this;
    }

    public function order(string $field, string $direction){
        $this->orderParams[] = [$field => $direction];
        return $this;
    }

    public function limit(int $size){
        $this->size = $size;
        return $this;
    }

    public function offset(int $from){
        $this->from = $from;
        return $this;
    }

    public function get(){
        return $this->search($this->buildQuery());
    }

    public function buildQuery(){
        return [
            'query' => ['bool' => $this->queryParams],
            'sort' => $this->orderParams ?: ($this->defaultOrder ?: []),
            'size' => $this->size ?: ($this->defaultSize ?: 1000),
            'from' => $this->from ?: 1
        ];
    }

    public function buildMatch($value, $field){
        return [
            "match" => [
                $field => $value
            ]
        ];
    }

    public function buildWildcard($value, $field){
        return [
            "wildcard" => [
                $field => $value
            ]
        ];
    }

    public function buildRange($range, $field = "@timestamp", array $options = null){
        $query = $range;
        $options && $query += $options;

        $query = [
            "range" =>[
                $field => $query
            ],
        ];

        return $query;
    }

    public function search(array $body)
    {
        $instance = new static;
        $params = $instance->basicElasticParams();

        $params['body'] = $body;

        $response = $instance->getElasticClient()->search($params);

        return new ElasticCollection($response, $instance);
    }

    protected function basicElasticParams($withId = false)
    {
        $params = array(
            'index' => $this->getIndex(),
            'type' => $this->getTypeName()
        );

        if ($withId and $this->getKey()) {
            $params['id'] = $this->getKey();
        }

        return $params;
    }

    public function getIndex()
    {
        if (isset($this->indexName)) {
            return $this->indexName;
        }

        return Config::get('md_elasticsearch.index');
    }

    public function getTypeName()
    {
        if (isset($this->typeName)) {
            return $this->typeName;
        }

        return $this->getTable();
    }

    public static function count(array $body)
    {
        $instance = new static;
        $params = $instance->basicElasticParams();
        $params['body'] = $body;
        $response = $instance->getElasticClient()->count($params);
        return intval($response['count']);
    }

    public function newFromElasticResults(array $hit)
    {
        if(! ($this instanceof Model)){
            return $hit;
        }

        $instance = $this->newInstance(array(), true);

        $attributes = $hit['_source'];

        $instance->isDocument = true;

        if (isset($hit['_score'])) {
            $instance->documentScore = $hit['_score'];
        }

        if (isset($hit['_version'])) {
            $instance->documentVersion = $hit['_version'];
        }

        if (isset($hit['highlight'])) {
            foreach ($hit['highlight'] as $field => $value) {
                $instance->highlighted[$field] = $value[0];
            }
        }

        $instance->setRawAttributes($attributes, true);

        return $instance;
    }

    protected function getElasticClient()
    {
        return ClientBuilder::create()
            ->setHosts(config('md_elasticsearch.hosts'))
            ->build();
    }







    /**
     * Builds a multi_match query.
     *
     * @param array $fields
     * @param string $query
     * @return ElasticCollection
     */
    public static function multiMatch(array $fields, $query)
    {
        $body = array(
            'query' => array(
                'multi_match' => array(
                    'query' => $query,
                    'fields' => $fields
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds a fuzzy query.
     *
     * @param string $field
     * @param string $value
     * @param string $fuzziness
     * @return ElasticCollection
     */
    public static function fuzzy($field, $value, $fuzziness = 'AUTO')
    {
        $body = array(
            'query' => array(
                'fuzzy' => array(
                    $field => array(
                        'value' => $value,
                        'fuzziness' => $fuzziness
                    )
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds an id query.
     *
     * @param array $values
     * @return ElasticCollection
     */
    public static function id($value)
    {
        $body = array(
            'query' => array(
                'id' => array(
                    'values' => $value
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds an ids query.
     *
     * @param array $values
     * @return ElasticCollection
     */
    public static function ids(array $values)
    {
        $body = array(
            'query' => array(
                'ids' => array(
                    'values' => $values
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Builds a more_like_this query.
     *
     * @param array $fields
     * @param array $ids
     * @param int $minTermFreq
     * @param float $percentTermsToMatch
     * @param int $minWordLength
     * @return ElasticCollection
     */
    public static function moreLikeThis(array $fields, array $ids, $minTermFreq = 1, $percentTermsToMatch = 0.5, $minWordLength = 3)
    {
        $body = array(
            'query' => array(
                'more_like_this' => array(
                    'fields' => $fields,
                    'ids' => $ids,
                    'min_term_freq' => $minTermFreq,
                    'percent_terms_to_match' => $percentTermsToMatch,
                    'min_word_length' => $minWordLength,
                )
            ),
            'size' => 1000
        );

        return static::search($body);
    }

    /**
     * Instructs Eloquent to use a custom
     * collection class.
     *
     * @param array $models
     * @return BouncyCollection
     */
    public function newCollection(array $models = array())
    {
        return new BouncyCollection($models);
    }



}