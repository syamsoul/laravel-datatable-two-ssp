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

                array_push($frontend_dt_cols, [
                    'title'=>$dt_col['label']
                ]);

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

        $db_cols = []; $db_cols_fake = []; $formatter = [];
        foreach($dt_cols as $dt_col){
            if(isset($dt_col['db'])) array_push($db_cols, $dt_col['db']);
            elseif(isset($dt_col['db_fake'])) array_push($db_cols_fake, $dt_col['db_fake']);

            if(isset($dt_col['formatter'])) $formatter[$dt_col['db'] ?? $dt_col['db_fake']] = $dt_col['formatter'];
        }

        $the_query = $this->dtQuery($db_cols);

        if($the_query != null){

            $the_query_count = $this->dtGetCount($the_query);

            if($frontend_framework == "datatablejs"){

                $the_query->orderBy($dt_cols[$request->order[0]["column"]]['db'], $request->order[0]['dir']);

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
                foreach($db_cols as $e_db_col){
                    if(isset($formatter[$e_db_col])){
                        if(is_callable($formatter[$e_db_col])) $the_query_data[$key][$e_db_col] = $formatter[$e_db_col]($e_tqde->{$e_db_col}, $e_tqde);
                        elseif(is_string($formatter[$e_db_col])) $the_query_data[$key][$e_db_col] = strtr($formatter[$e_db_col], ["{value}"=>$e_tqde->{$e_db_col}]);
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
                    $pair_key_column_index[$dt_col['db'] ?? $dt_col['db_fake']] = $key;
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
