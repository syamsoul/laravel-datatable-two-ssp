<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use SoulDoit\DataTableTwo\Exceptions\PageAndItemsPerPageParametersAreRequired;
use SoulDoit\DataTableTwo\Exceptions\InvalidItemsPerPageValue;

trait Query{
    private $dt_query;
    private $query_count;
    private $query_custom_filter;
    private $pagination_data;

    protected function query(array $selected_columns)
    {
        return is_callable($this->dt_query) ? ($this->dt_query)($selected_columns) : $this->dt_query;
    }


    protected function queryCount(EloquentBuilder|QueryBuilder $the_query)
    {
        if($this->query_count == null){
            if($the_query instanceof EloquentBuilder){
                if(!empty($the_query->getQuery()->groups)) return $the_query->getQuery()->getCountForPagination();
            }
            
            return $the_query->count();
        }
        return is_callable($this->query_count) ? ($this->query_count)($the_query) : $this->query_count;
    }


    public function setQuery(callable|EloquentBuilder|QueryBuilder $query)
    {
        $this->dt_query = $query;

        return $this;
    }


    public function setQueryCount(callable|int $query_count)
    {
        $this->query_count = $query_count;

        return $this;
    }


    private function queryOrder(EloquentBuilder|QueryBuilder $the_query)
    {
        $request = request();

        $frontend_framework = isset($this->frontend_framework) ? $this->frontend_framework : config('sd-datatable-two-ssp.frontend_framework', 'others');

        $arranged_cols_details = $this->getArrangedColsDetails();
        $db_cols_mid = $arranged_cols_details['db_cols_mid'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];

        if($frontend_framework == "datatablejs"){

            if($request->filled('order')){
                $the_query->orderBy($db_cols_mid[$request->order[0]["column"]], $request->order[0]['dir']);
            }

        }elseif(in_array($frontend_framework, ["vuetify", "others"])){

            if($request->filled('sortBy') && $request->filled('sortDesc')){
                $request->validate([
                    'sortDesc' => ['in:1,0,true,false'],
                ],[
                    'sortDesc.in' => 'Sort desc must be either 1,0,true or false',
                ]);

                $sortDesc = $request->sortDesc;
                if(is_string($request->sortDesc) || is_numeric($request->sortDesc)){
                    $sortDesc = filter_var($request->sortDesc, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }

                $the_query->orderBy($db_cols_mid[array_flip($db_cols_final)[$request->sortBy]], ($sortDesc ? 'desc':'asc'));
            }

        }

        return $the_query;
    }


    private function queryPagination(EloquentBuilder|QueryBuilder $the_query)
    {
        $request = request();

        $pagination_data = $this->getPaginationData();

        if(isset($pagination_data['items_per_page']) && isset($pagination_data['offset'])){
            if(isset($this->allowed_items_per_page)){
                $allowed_items_per_page = is_numeric($this->allowed_items_per_page) ? [$this->allowed_items_per_page] : (is_array($this->allowed_items_per_page) ? $this->allowed_items_per_page : null);
                
                if(is_array($allowed_items_per_page)){
                    $allowed_items_per_page = array_map(function($v){ return intval($v); }, $allowed_items_per_page);

                    if(!in_array($pagination_data['items_per_page'], $allowed_items_per_page)){
                        throw InvalidItemsPerPageValue::create($pagination_data['items_per_page'], $allowed_items_per_page);
                    }
                }
            }

            if($pagination_data['items_per_page'] != "-1") $the_query->limit($pagination_data['items_per_page'])->offset($pagination_data['offset']);
        }

        return $the_query;
    }


    private function getPaginationData()
    {
        if($this->pagination_data !== null) return $this->pagination_data;

        $request = request();

        $ret = [];

        $frontend_framework = isset($this->frontend_framework) ? $this->frontend_framework : config('sd-datatable-two-ssp.frontend_framework', 'others');

        if($frontend_framework == "datatablejs"){

            $firstRequestName = 'start';
            $secondRequestName = 'length';

            if($request->filled('length') && $request->filled('start')){
                $ret['items_per_page'] = $request->length;
                $ret['offset'] = $request->start;
            }

        }elseif(in_array($frontend_framework, ["vuetify", "others"])){

            $firstRequestName = 'page';
            $secondRequestName = 'itemsPerPage';

            if($request->filled('itemsPerPage') && $request->filled('page')){
                $ret['items_per_page'] = $request->itemsPerPage;
                $ret['offset'] = ($request->page - 1) * $request->itemsPerPage;    
            }

        }

        if(empty($ret)){
            if(isset($this->allowed_items_per_page)){
                if(is_numeric($this->allowed_items_per_page) || is_array($this->allowed_items_per_page)){
                    $aipp = $this->allowed_items_per_page;
                    if(is_numeric($aipp)) $aipp = [$aipp];
                    if(!in_array(-1, $aipp)) throw PageAndItemsPerPageParametersAreRequired::create($firstRequestName, $secondRequestName);
                }
            }
        }

        $request->validate([
            $firstRequestName => ['required_with:'.$secondRequestName],
            $secondRequestName => ['required_with:'.$firstRequestName],
        ]);

        $this->pagination_data = $ret;

        return $ret;
    }


    protected function queryCustomFilter(EloquentBuilder|QueryBuilder $the_query)
    {
        if($this->query_custom_filter == null) return $the_query;

        return is_callable($this->query_custom_filter) ? ($this->query_custom_filter)($the_query) : $this->query_custom_filter;
    }


    public function setQueryCustomFilter(callable|EloquentBuilder|QueryBuilder $query)
    {
        $this->query_custom_filter = $query;

        return $this;
    }


    private function querySearch(EloquentBuilder|QueryBuilder $the_query)
    {
        $search_value = $this->getSearchValue();

        if(!empty($search_value)){
            $arranged_cols_details = $this->getArrangedColsDetails();
            $db_cols_initial = $arranged_cols_details['db_cols_initial'];
            $db_cols_mid = $arranged_cols_details['db_cols_mid'];
            $db_cols_final = $arranged_cols_details['db_cols_final'];

            $the_query = $the_query->where(function($query) use($db_cols_initial, $search_value){
                foreach($db_cols_initial as $index=>$e_col){
                    if($index == 0) $query->where($e_col, 'LIKE', "%".$search_value."%");
                    else $query->orWhere($e_col, 'LIKE', "%".$search_value."%");
                }
            });
        }

        return $the_query;
    }


    private function getSearchValue() : string
    {
        $is_search_enable = isset($this->is_search_enable) ? $this->is_search_enable : false;
        if(!$is_search_enable) return '';

        $request = request();
        $frontend_framework = isset($this->frontend_framework) ? $this->frontend_framework : config('sd-datatable-two-ssp.frontend_framework', 'others');

        $search_value = '';

        if($frontend_framework == "datatablejs"){

            if($request->filled('search')){
                $search_value = $request->search['value'] ?? '';
            }

        }elseif(in_array($frontend_framework, ["vuetify", "others"])){

            if($request->filled('search')){
                $search_value = $request->search;
            }

        }

        return $search_value;
    }


    public function enableSearch(bool $enable = true)
    {
        $this->is_search_enable = $enable;

        return $this;
    }


    public function setAllowedItemsPerPage(int|array $allowed_items_per_page)
    {
        $this->allowed_items_per_page = $allowed_items_per_page;

        return $this;
    }
}
