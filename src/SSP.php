<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Http\Request;

trait SSP{
    /*
    |--------------------------------------------------------------------------
    | DataTable SSP for Laravel
    |--------------------------------------------------------------------------
    |
    | Author    : Syamsoul Azrien Muda (+60139584638)
    | Website   : https://github.com/syamsoulcc
    |
    */

    private function dtColumns()
    {
        return [];
    }


    private function dtQuery($selected_columns=null)
    {
        return null;
    }


    private function dtGetFrontEndColumns()
    {
        $frontend_framework = config('sd-datatable-two-ssp.frontend_framework');

        $dt_cols = $this->dtColumns();

        $frontend_dt_cols = [];
        foreach($dt_cols as $dt_col){
            if($frontend_framework == "datatablejs"){

                $e_fe_dt_col = ['title'=>$dt_col['label']];
                if(isset($dt_col['class'])){
                    if(is_array($dt_col['class'])) $e_fe_dt_col['className'] = implode(" ", $dt_col['class']);
                    else if(is_string($dt_col['class'])) $e_fe_dt_col['className'] = $dt_col['class'];
                }
                if(isset($dt_col['orderable'])){
                    if(is_bool($dt_col['orderable'])) $e_fe_dt_col['orderable'] = $dt_col['orderable'];
                }
                array_push($frontend_dt_cols, $e_fe_dt_col);

            }elseif($frontend_framework == "vuetify"){

                array_push($frontend_dt_cols, [
                    'text' => $dt_col['label'],
                    'value' => $dt_col['db'] ?? $dt_col['db_fake'],
                ]);

            }
        }

        return $frontend_dt_cols;
    }


    public function dtGetData(Request $request)
    {
        $ret = ['success'=>false];

        $frontend_framework = config('sd-datatable-two-ssp.frontend_framework');

        $dt_cols = $this->dtColumns();

        $db_cols = []; $db_cols_mid = []; $db_cols_final = []; $db_cols_fake = []; $formatter = [];
        foreach($dt_cols as $key=>$dt_col){
            if(isset($dt_col['db'])){
                $db_cols[$key] = $dt_col['db'];
                $dt_col_db_arr = explode(" AS ", $dt_col['db']);
                if(count($dt_col_db_arr) == 2){
                    $db_cols_final[$key] = $dt_col_db_arr[1];
                    $db_cols_mid[$key] = $dt_col_db_arr[1];
                }else{
                    $dt_col_db_arr = explode(".", $dt_col['db']);
                    if(count($dt_col_db_arr) == 2) $db_cols_final[$key] = $dt_col_db_arr[1];
                    else $db_cols_final[$key] = $dt_col['db'];

                    $db_cols_mid[$key] = $dt_col['db'];
                }
            }elseif(isset($dt_col['db_fake'])) $db_cols_fake[$key] = $dt_col['db_fake'];

            if(isset($dt_col['formatter'])) $formatter[$dt_col['db'] ?? $dt_col['db_fake']] = $dt_col['formatter'];
        }

        $the_query = $this->dtQuery($db_cols);

        if($the_query != null){

            $the_query_count = $this->dtGetCount($the_query);

            if($frontend_framework == "datatablejs"){

                $the_query->orderBy($db_cols_mid[$request->order[0]["column"]], $request->order[0]['dir']);

                if($request->length != "-1") $the_query->limit($request->length)->offset($request->start);

            }elseif($frontend_framework == "vuetify"){

                if($request->filled('sortBy') && $request->filled('sortDesc')){
                    $the_query->orderBy($request->sortBy, ($request->sortDesc == 'true' ? 'desc':'asc'));
                }

                if($request->itemsPerPage != "-1") $the_query->limit($request->itemsPerPage)->offset(($request->page - 1) * $request->itemsPerPage);

            }

            $the_query_data_eloq = $the_query->get();

            $the_query_data = [];
            foreach($the_query_data_eloq as $key=>$e_tqde){
                $the_query_data[$key] = [];
                foreach($db_cols_final as $key_2=>$e_db_col){
                    if(isset($formatter[$db_cols[$key_2]])){
                        if(is_callable($formatter[$db_cols[$key_2]])) $the_query_data[$key][$e_db_col] = $formatter[$db_cols[$key_2]]($e_tqde->{$e_db_col}, $e_tqde);
                        elseif(is_string($formatter[$db_cols[$key_2]])) $the_query_data[$key][$e_db_col] = strtr($formatter[$db_cols[$key_2]], ["{value}"=>$e_tqde->{$e_db_col}]);
                    }else{
                        $the_query_data[$key][$e_db_col] = $e_tqde->{$e_db_col};
                    }
                }
                foreach($db_cols_fake as $e_db_col){
                    $the_query_data[$key][$e_db_col] = $formatter[$e_db_col]($e_tqde);
                }
            }


            if($frontend_framework == "datatablejs"){

                $pair_key_column_index = [];
                foreach($dt_cols as $key=>$dt_col){
                    if(isset($dt_col['db'])) $pair_key_column_index[$db_cols_final[$key]] = $key;
                    else $pair_key_column_index[$dt_col['db_fake']] = $key;
                }

                $new_query_data = [];
                foreach($the_query_data as $key=>$e_tqdata){
                    $e_new_cols_data = [];
                    foreach($e_tqdata as $e_e_col_name=>$e_e_col_value){
                        $e_new_cols_data[$pair_key_column_index[$e_e_col_name]] = $e_e_col_value;
                    }
                    $new_query_data[$key] = $e_new_cols_data;
                }

                $ret['draw'] = $request->draw ?? 0;
                $ret['data'] = $new_query_data;
                $ret['recordsTotal'] = $the_query_count;
                $ret['recordsFiltered'] = $the_query_count; // NOTE: currently filter not functioning yet

            }elseif($frontend_framework == "vuetify"){

                $ret['data'] = [
                    'items' => $the_query_data,
                    'total_item_count' => $the_query_count,
                ];

            }

            $ret['success'] = true;

        }

        return response()->json($ret);
    }


    private function dtGetCount($query=null)
    {
        if($query!=null){
            return $query->count();
        }

        return 0;
    }
}
