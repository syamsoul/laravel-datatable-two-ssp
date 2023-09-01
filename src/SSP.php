<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use SoulDoit\DataTableTwo\Exceptions\RawExpressionMustHaveAliasName;
use SoulDoit\DataTableTwo\Exceptions\ValueInCsvColumnsMustBeString;
use SoulDoit\DataTableTwo\Query;
use ReflectionMethod;

class SSP
{
    use Query;

    private $dt_columns;
    private $arranged_cols_details;
    private $db_fake_identifier = '||-----FAKE-----||';
    
    protected $frontend_framework = null;

    protected function columns()
    {
        return $this->dt_columns;
    }


    public function setColumns(array $columns)
    {
        $this->dt_columns = $columns;

        return $this;
    }


    private function getColumns(): array
    {
        return array_map(function($v) {
            if (!is_array($v)) return ['db' => $v];
            return $v;
        }, $this->columns());
    }


    public function getFrontEndColumns(): array
    {
        $frontend_framework = $this->frontend_framework ?? config('sd-datatable-two-ssp.frontend_framework', 'others');

        $dt_cols = $this->getColumns();

        $frontend_dt_cols = [];

        foreach ($dt_cols as $dt_col) {
            if (isset($dt_col['db'])) {
                $is_db_raw = ($dt_col['db'] instanceof Expression);

                $dt_col_db_arr = $this->getDtColDbArray($dt_col['db'], $is_db_raw);

                if (count($dt_col_db_arr) == 2) {
                    $db_col = $is_db_raw ? str_replace("`", "", $dt_col_db_arr[1]) : $dt_col_db_arr[1];
                } else {
                    if ($is_db_raw) throw RawExpressionMustHaveAliasName::create($this->getRawExpressionValue($dt_col['db']));

                    $dt_col_db_arr = explode(".", $dt_col['db']);
                    if (count($dt_col_db_arr) == 2) $db_col = $dt_col_db_arr[1];
                    else $db_col = $dt_col['db'];
                }
            } else if (isset($dt_col['db_fake'])) $db_col = $dt_col['db_fake'];

            $dt_col_label = $dt_col['label'] ?? ucwords(str_replace("_", " ", Str::snake($db_col)));
            $sortable = (!isset($dt_col['db']) && isset($dt_col['db_fake'])) ? false : (isset($dt_col['sortable']) ? $dt_col['sortable'] : true);

            if ($frontend_framework == "datatablejs") {

                $e_fe_dt_col = ['title' => $dt_col_label];

                if (isset($dt_col['class'])) {
                    if (is_array($dt_col['class'])) $e_fe_dt_col['className'] = implode(" ", $dt_col['class']);
                    else if (is_string($dt_col['class'])) $e_fe_dt_col['className'] = $dt_col['class'];
                }

                $e_fe_dt_col['orderable'] = $sortable;

                array_push($frontend_dt_cols, $e_fe_dt_col);

            } else if (in_array($frontend_framework, ["vuetify", "others"])) {

                if ($frontend_framework == "vuetify") {
                    array_push($frontend_dt_cols, [
                        'text' => $dt_col_label,
                        'value' => $db_col,
                    ]);
                } else if ($frontend_framework == "others") {
                    array_push($frontend_dt_cols, [
                        'label' => $dt_col_label,
                        'db' => $db_col,
                        'class' => $dt_col['class'] ?? [],
                        'sortable' => $sortable,
                    ]);
                }

            }
        }

        return $frontend_dt_cols;
    }


    public function getData(bool $return_json = true)
    {
        $request = request();
        
        $ret = ['success' => false];

        $frontend_framework = $this->frontend_framework ?? config('sd-datatable-two-ssp.frontend_framework', 'others');

        $arranged_cols_details = $this->getArrangedColsDetails();
        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols = $arranged_cols_details['db_cols'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];

        $the_query = $this->query($db_cols);

        if ($the_query != null) {

            $the_query_count = $this->queryCount($the_query);

            $the_query = $this->querySearch($the_query);

            $has_custom_filter_query = false;

            if ($this->query_custom_filter != null || $this->isMethodOverridden('queryCustomFilter')) {
                $the_custom_filter_query = $this->queryCustomFilter($the_query);
                
                if (gettype($the_custom_filter_query) == 'object') {
                    if ($the_custom_filter_query instanceof EloquentBuilder || $the_custom_filter_query instanceof QueryBuilder) {
                        $has_custom_filter_query = true;
                        $the_query = $the_custom_filter_query;
                    }
                }
            }

            $the_query_filtered_count = (empty($this->getSearchValue()) && !$has_custom_filter_query) ? $the_query_count : $this->queryCount($the_query);

            $the_query = $this->queryOrder($the_query);
            $the_query = $this->queryPagination($the_query);

            $the_query_data = $this->getFormattedData($the_query);

            if ($frontend_framework == "datatablejs") {

                $pair_key_column_index = [];
                foreach ($dt_cols as $key => $dt_col) {
                    if (isset($dt_col['db'])) $pair_key_column_index[$db_cols_final[$key]] = $key;
                    else $pair_key_column_index[$dt_col['db_fake']] = $key;
                }

                $new_query_data = [];
                foreach ($the_query_data as $key => $e_tqdata) {
                    $e_new_cols_data = [];
                    foreach ($e_tqdata as $e_e_col_name => $e_e_col_value) {
                        $e_new_cols_data[$pair_key_column_index[$e_e_col_name]] = $e_e_col_value;
                    }
                    $new_query_data[$key] = $e_new_cols_data;
                }

                $ret['draw'] = $request->draw ?? 0;
                $ret['data'] = $new_query_data;
                $ret['recordsTotal'] = $the_query_count;
                $ret['recordsFiltered'] = $the_query_filtered_count;

            } else if (in_array($frontend_framework, ["vuetify", "others"])) {

                $ret['data'] = [];

                $pagination_data = $this->getPaginationData();

                if (!empty($pagination_data)) {
                    $current_page_item_count = count($the_query_data);
                    $current_item_position_start = $current_page_item_count == 0 ? 0 : ($pagination_data['offset'] + 1);
                    $current_item_position_end = $current_page_item_count == 0 ? 0 : ($current_item_position_start + $current_page_item_count) - 1;

                    $ret['data'] = array_merge($ret['data'], [
                        'current_item_position_start' => $current_item_position_start,
                        'current_item_position_end' => $current_item_position_end,
                        'current_page_item_count' => $current_page_item_count,
                    ]);
                }

                $ret['data'] = array_merge($ret['data'], [
                    'total_item_count' => $the_query_count,
                    'total_filtered_item_count' => $the_query_filtered_count,
                    'items' => $the_query_data,
                ]);
            }

            $ret['success'] = true;

        }

        if ($return_json) return response()->json($ret);
        
        return $ret;
    }


    public function getCsvFile()
    {
        $is_cache_lock_enable = config('sd-datatable-two-ssp.export_to_csv.is_cache_lock_enable', false);

        if ($is_cache_lock_enable) {
            $lock_name = 'export-csv-'.request()->route()->getName();
            if (config('sd-datatable-two-ssp.export_to_csv.is_cache_lock_based_on_auth')) {
                $current_user = auth()->user();
                if (!empty($current_user)) $lock_name .= '-'.$current_user->id;
            }
            $lock = Cache::lock($lock_name, 3600); // lock for 1 hour

            $retry_count = 0;

            while (!$lock->get() && $retry_count<5) {
                $retry_count++;
                usleep(1500000);
            }

            if ($retry_count == 5) abort(408, "Currently, there's another proccess is running. Please try again later.");
        }

        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename='.strtr(request()->route()->getName(), ".", "-") ."-".now()->format("YmdHis").'.csv',
            'Expires'             => '0',
            'Pragma'              => 'public'
        ];

        $the_query = $this->query($this->getArrangedColsDetails()['db_cols']);

        $the_query = $this->querySearch($the_query);

        if ($this->query_custom_filter != null || $this->isMethodOverridden('queryCustomFilter')) {
            $the_custom_filter_query = $this->queryCustomFilter($the_query);
            
            if (gettype($the_custom_filter_query) == 'object') {
                if ($the_custom_filter_query instanceof EloquentBuilder || $the_custom_filter_query instanceof QueryBuilder) {
                    $the_query = $the_custom_filter_query;
                }
            }
        }

        $the_query = $this->queryOrder($the_query);

        $the_query_data = $this->getFormattedData($the_query, true);

        // add headers for each column in the CSV download
        $dt_cols = $this->getColumns();
        foreach ($dt_cols as $index => $e_dt_col) {
            if (! ($e_dt_col['is_include_in_doc'] ?? true)) unset($dt_cols[$index]);
        }
        array_unshift($the_query_data, collect($dt_cols)->pluck('label')->toArray());

        //check if value in each columns is string
        foreach ($the_query_data as $row) {
            foreach ($row as $e_col) {
                if (!is_string($e_col) && !is_numeric($e_col)) {
                    if ($is_cache_lock_enable) $lock->release();
                    throw ValueInCsvColumnsMustBeString::create(json_encode($e_col));
                }
            }
        }

        $callback = function() use($the_query_data) {
            $file = fopen('php://output', 'w');
            foreach ($the_query_data as $row) fputcsv($file, $row);
            fclose($file);
        };

        if ($is_cache_lock_enable) $lock->release();

        return response()->stream($callback, 200, $headers);
    }


    private function getArrangedColsDetails(bool $is_for_doc = false) : array
    {
        if (!$is_for_doc) {
            if ($this->arranged_cols_details != null) return $this->arranged_cols_details;
        }

        $dt_cols = $this->getColumns();

        $db_cols = $db_cols_initial = $db_cols_mid = $db_cols_final = $formatter = [];

        foreach ($dt_cols as $key => $dt_col) {
            if ($is_for_doc) {
                if (! ($dt_col['is_include_in_doc'] ?? true)) continue;
            }

            if (isset($dt_col['db'])) {
                $db_cols[$key] = $dt_col['db'];

                $is_db_raw = ($dt_col['db'] instanceof Expression);

                $dt_col_db_arr = $this->getDtColDbArray($dt_col['db'], $is_db_raw);

                if (count($dt_col_db_arr) == 2) {
                    $db_cols_final[$key] = $is_db_raw ? str_replace("`", "", $dt_col_db_arr[1]) : $dt_col_db_arr[1];
                    $db_cols_mid[$key] = $is_db_raw ? str_replace("`", "", $dt_col_db_arr[1]) : $dt_col_db_arr[1];
                    $db_cols_initial[$key] = $is_db_raw ? DB::raw($dt_col_db_arr[0]) : $dt_col_db_arr[0];
                } else {
                    if ($is_db_raw) throw RawExpressionMustHaveAliasName::create($this->getRawExpressionValue($dt_col['db']));

                    $dt_col_db_arr = explode(".", $dt_col['db']);

                    if (count($dt_col_db_arr) == 2) $db_cols_final[$key] = $dt_col_db_arr[1];
                    else $db_cols_final[$key] = $dt_col['db'];

                    $db_cols_mid[$key] = $dt_col['db'];
                    $db_cols_initial[$key] = $dt_col['db'];
                }
            } else if (isset($dt_col['db_fake'])) {
                $db_cols_final[$key] = $dt_col['db_fake'] . $this->db_fake_identifier;
                $db_cols_mid[$key] = $dt_col['db_fake'] . $this->db_fake_identifier;
                $db_cols_initial[$key] = $dt_col['db_fake'] . $this->db_fake_identifier;
            }

            if (isset($dt_col['formatter'])) $formatter[$key] = $dt_col['formatter'];

            if ($is_for_doc) {
                if (isset($dt_col['formatter_doc'])) $formatter[$key] = $dt_col['formatter_doc'];
            }
        }

        $arranged_cols_details = [
            'dt_cols' => $dt_cols,
            'db_cols' => $db_cols,
            'db_cols_initial' => $db_cols_initial,
            'db_cols_mid' => $db_cols_mid,
            'db_cols_final' => $db_cols_final,
            'formatter' => $formatter,
        ];

        if (!$is_for_doc) $this->arranged_cols_details = $arranged_cols_details;

        return $arranged_cols_details;
    }


    private function getFormattedData(EloquentBuilder|QueryBuilder $the_query, bool $is_for_doc = false) : array
    {
        $the_query_data_eloq = $the_query->get();

        $arranged_cols_details = $this->getArrangedColsDetails($is_for_doc);
        $db_cols = $arranged_cols_details['db_cols'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];
        $formatter = $arranged_cols_details['formatter'];

        $the_query_data = [];
        foreach ($the_query_data_eloq as $key => $e_tqde) {
            $the_query_data[$key] = [];
            foreach ($db_cols_final as $key_2 => $e_db_col) {
                $e_db_col_filtered = trim(str_replace($this->db_fake_identifier, "", $e_db_col));
                if (strpos($e_db_col, $this->db_fake_identifier) !== false) {
                    $the_query_data[$key][$e_db_col_filtered] = $formatter[$key_2]($e_tqde);
                } else {
                    if (isset($formatter[$key_2])) {
                        if(is_callable($formatter[$key_2])) $the_query_data[$key][$e_db_col_filtered] = $formatter[$key_2]($e_tqde->{$e_db_col_filtered}, $e_tqde);
                        elseif(is_string($formatter[$key_2])) $the_query_data[$key][$e_db_col_filtered] = strtr($formatter[$key_2], ["{value}"=>$e_tqde->{$e_db_col_filtered}]);
                    } else {
                        $the_query_data[$key][$e_db_col_filtered] = $e_tqde->{$e_db_col_filtered};
                    }
                }
                if ($is_for_doc) $the_query_data[$key][$e_db_col_filtered] = strip_tags($the_query_data[$key][$e_db_col_filtered]);
            }
        }

        return $the_query_data;
    }

    public function setFrontendFramework(string $frontend_framework)
    {
        $this->frontend_framework = $frontend_framework;

        return $this;
    }

    private function getRawExpressionValue(Expression $raw_expression)
    {
        $is_laravel_version_ten = intval(app()->version()) >= 10;

        if ($is_laravel_version_ten) return $raw_expression->getValue(DB::connection()->getQueryGrammar());
        else return $raw_expression->getValue();
    }

    private function getDtColDbArray(string|Expression $db_col, bool $is_db_raw): array
    {
        $db_col = $is_db_raw ? $this->getRawExpressionValue($db_col) : $db_col;

        return explode(" as ", preg_replace("/ as /i", " as ", $db_col));
    }
    
    private function isMethodOverridden(string $method_name)
    {
        $current_class = get_class($this);
        if ($current_class === 'SoulDoit\DataTableTwo\SSP') return false;
        
        $reflector = new ReflectionMethod($this, $method_name);
        return ($reflector->getDeclaringClass()->getName() === $current_class);
    }
}
