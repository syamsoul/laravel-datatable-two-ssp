<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait Query{
    private $dt_query;
    private $query_count;
    private $pagination_data;

    private function dtQuery(array $selected_columns = null)
    {
        return is_callable($this->dt_query) ? ($this->dt_query)($selected_columns) : $this->dt_query;
    }


    private function queryCount(EloquentBuilder|QueryBuilder $the_query) : int
    {
        if($this->query_count == null) return $the_query->count();
        return is_callable($this->query_count) ? ($this->query_count)($the_query) : $this->query_count;
    }


    private function setDtQuery(callable|EloquentBuilder|QueryBuilder $dt_query)
    {
        $this->dt_query = $dt_query;
    }


    private function setQueryCount(callable|int $query_count)
    {
        $this->query_count = $query_count;
    }


    private function queryOrder(EloquentBuilder|QueryBuilder $the_query)
    {
        $request = request();

        $frontend_framework = config('sd-datatable-two-ssp.frontend_framework');

        $arranged_cols_details = $this->getArrangedColsDetails();
        $db_cols_mid = $arranged_cols_details['db_cols_mid'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];

        if($frontend_framework == "datatablejs"){

            if($request->filled('order')){
                $the_query->orderBy($db_cols_mid[$request->order[0]["column"]], $request->order[0]['dir']);
            }

        }elseif(in_array($frontend_framework, ["vuetify", "others"])){

            if($request->filled('sortBy') && $request->filled('sortDesc')){
                $the_query->orderBy($db_cols_mid[array_flip($db_cols_final)[$request->sortBy]], ($request->sortDesc == 'true' ? 'desc':'asc'));
            }

        }

        return $the_query;
    }


    private function queryPagination(EloquentBuilder|QueryBuilder $the_query)
    {
        $request = request();

        $pagination_data = $this->getPaginationData();

        if(isset($pagination_data['items_per_page']) && isset($pagination_data['offset'])){
            if($pagination_data['items_per_page'] != "-1") $the_query->limit($pagination_data['items_per_page'])->offset($pagination_data['offset']);
        }

        return $the_query;
    }


    private function getPaginationData()
    {
        if($this->pagination_data !== null) return $this->pagination_data;

        $request = request();

        $ret = [];

        $frontend_framework = config('sd-datatable-two-ssp.frontend_framework');

        if($frontend_framework == "datatablejs"){

            if($request->filled('length') && $request->filled('start')){
                $ret['items_per_page'] = $request->length;
                $ret['offset'] = $request->start;
            }

        }elseif(in_array($frontend_framework, ["vuetify", "others"])){

            if($request->filled('itemsPerPage') && $request->filled('page')){
                $ret['items_per_page'] = $request->itemsPerPage;
                $ret['offset'] = ($request->page - 1) * $request->itemsPerPage;
            }

        }

        $this->pagination_data = $ret;

        return $ret;
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
        $frontend_framework = config('sd-datatable-two-ssp.frontend_framework');

        $search_value = '';

        if($frontend_framework == "datatablejs"){

            if($request->filled('search')){
                $search_value = $request->search['value'];
            }

        }elseif(in_array($frontend_framework, ["vuetify", "others"])){

            if($request->filled('search')){
                $search_value = $request->search;
            }

        }

        return $search_value;
    }
}
