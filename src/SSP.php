<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SoulDoit\DataTableTwo\Query;

trait SSP{
	use Query;

	private $arranged_cols_details;


	private function dtColumns()
	{
		return [];
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

			}elseif(in_array($frontend_framework, ["vuetify", "others"])){

				if(isset($dt_col['db'])){
					$dt_col_db_arr = explode(" AS ", $dt_col['db']);
					if(count($dt_col_db_arr) == 2){
						$db_col = $dt_col_db_arr[1];
					}else{
						$dt_col_db_arr = explode(".", $dt_col['db']);
						if(count($dt_col_db_arr) == 2) $db_col = $dt_col_db_arr[1];
						else $db_col = $dt_col['db'];
					}
				}elseif(isset($dt_col['db_fake'])) $db_col = $dt_col['db_fake'];

				if($frontend_framework == "vuetify"){
					array_push($frontend_dt_cols, [
						'text' => $dt_col['label'],
						'value' => $db_col,
					]);
				}elseif($frontend_framework == "others"){
					array_push($frontend_dt_cols, [
						'label' => $dt_col['label'],
						'db' => $db_col,
						'class' => $dt_col['class'] ?? [],
                        'sortable' => (!isset($dt_col['db']) && isset($dt_col['db_fake'])) ? false : (isset($dt_col['sortable']) ? $dt_col['sortable'] : true),
					]);
				}

			}
		}

		return $frontend_dt_cols;
	}


	public function dtGetData(Request $request)
	{
		$ret = ['success'=>false];

		$frontend_framework = config('sd-datatable-two-ssp.frontend_framework');

		$arranged_cols_details = $this->getArrangedColsDetails();
		$dt_cols = $arranged_cols_details['dt_cols'];
		$db_cols = $arranged_cols_details['db_cols'];
		$db_cols_mid = $arranged_cols_details['db_cols_mid'];
		$db_cols_final = $arranged_cols_details['db_cols_final'];

		$the_query = $this->dtQuery($db_cols);

		if($the_query != null){

			$the_query_count = $this->dtGetCount($the_query);

			$the_query = $this->queryOrder($the_query);
			$the_query = $this->queryPagination($the_query);
			$the_query = $this->querySearch($the_query);

			$the_query_filtered_count = $this->dtGetCount($the_query);

			$the_query_data = $this->getFormattedData($the_query);

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
				$ret['recordsFiltered'] = $the_query_filtered_count;

			}elseif(in_array($frontend_framework, ["vuetify", "others"])){

				$ret['data'] = [
					'items' => $the_query_data,
					'total_item_count' => $the_query_count,
					'total_filtered_item_count' => $the_query_filtered_count,
				];

			}

			$ret['success'] = true;

		}

		return response()->json($ret);
	}


	public function dtGtCsvFile()
	{
		$lock_name = 'export-csv-'.request()->route()->getName();
		if(config('sd-datatable-two-ssp.export_to_csv.is_cache_lock_based_on_auth')){
			$current_user = auth()->user();
			if(!empty($current_user)) $lock_name .= '-'.$current_user->id;
		}
		$lock = Cache::lock($lock_name, 3600); // lock for 1 hour

		$retry_count = 0;

		while(!$lock->get() && $retry_count<5){
			$retry_count++;
			usleep(1500000);
		}

		if($retry_count == 5) abort(408, "Currently, there's another proccess is running. Please try again later.");

		$headers = [
			'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
			'Content-type'        => 'text/csv',
			'Content-Disposition' => 'attachment; filename='.strtr(request()->route()->getName(), ".", "-") ."-".now()->format("YmdHis").'.csv',
			'Expires'             => '0',
			'Pragma'              => 'public'
		];

		$the_query = $this->dtQuery($this->getArrangedColsDetails()['db_cols']);

		$the_query = $this->queryOrder($the_query);
		$the_query = $this->querySearch($the_query);

		$the_query_data = $this->getFormattedData($the_query, true);

		// add headers for each column in the CSV download
		$dt_cols = $this->dtColumns();
		foreach($dt_cols as $index=>$e_dt_col) if(isset($e_dt_col['is_include_in_doc'])) if(!$e_dt_col['is_include_in_doc']) unset($dt_cols[$index]);
		array_unshift($the_query_data, collect($dt_cols)->pluck('label')->toArray());

		$callback = function() use ($the_query_data){
			$file = fopen('php://output', 'w');
			foreach ($the_query_data as $row) fputcsv($file, $row);
			fclose($file);
		};

		$lock->release();

		return response()->stream($callback, 200, $headers);

	}


	private function getArrangedColsDetails($is_for_doc=false)
	{
		if(!$is_for_doc){
			if($this->arranged_cols_details != null) return $this->arranged_cols_details;
		}

		$dt_cols = $this->dtColumns();

		$db_cols = []; $db_cols_initial = []; $db_cols_mid = []; $db_cols_final = []; $db_cols_fake = []; $formatter = [];
		foreach($dt_cols as $key=>$dt_col){
			if($is_for_doc) if(isset($dt_col['is_include_in_doc'])) if(!$dt_col['is_include_in_doc']) continue;
			if(isset($dt_col['db'])){
				$db_cols[$key] = $dt_col['db'];
				$dt_col_db_arr = explode(" AS ", $dt_col['db']);
				if(count($dt_col_db_arr) == 2){
					$db_cols_final[$key] = $dt_col_db_arr[1];
					$db_cols_mid[$key] = $dt_col_db_arr[1];
					$db_cols_initial[$key] = $dt_col_db_arr[0];
				}else{
					$dt_col_db_arr = explode(".", $dt_col['db']);
					if(count($dt_col_db_arr) == 2) $db_cols_final[$key] = $dt_col_db_arr[1];
					else $db_cols_final[$key] = $dt_col['db'];

					$db_cols_mid[$key] = $dt_col['db'];
					$db_cols_initial[$key] = $dt_col['db'];
				}
			}elseif(isset($dt_col['db_fake'])) $db_cols_fake[$key] = $dt_col['db_fake'];

			if(isset($dt_col['formatter'])) $formatter[$dt_col['db'] ?? $dt_col['db_fake']] = $dt_col['formatter'];
			if($is_for_doc){
				if(isset($dt_col['formatter_doc'])) $formatter[$dt_col['db'] ?? $dt_col['db_fake']] = $dt_col['formatter_doc'];
			}
		}

		$arranged_cols_details = [
			'dt_cols' => $dt_cols,
			'db_cols' => $db_cols,
			'db_cols_initial' => $db_cols_initial,
			'db_cols_mid' => $db_cols_mid,
			'db_cols_final' => $db_cols_final,
			'db_cols_fake' => $db_cols_fake,
			'formatter' => $formatter,
		];

		if(!$is_for_doc) $this->arranged_cols_details = $arranged_cols_details;

		return $arranged_cols_details;
	}


	private function getFormattedData($the_query, $is_for_doc=false)
	{
		$the_query_data_eloq = $the_query->get();

		$arranged_cols_details = $this->getArrangedColsDetails($is_for_doc);
		$db_cols = $arranged_cols_details['db_cols'];
		$db_cols_final = $arranged_cols_details['db_cols_final'];
		$db_cols_fake = $arranged_cols_details['db_cols_fake'];
		$formatter = $arranged_cols_details['formatter'];

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

		return $the_query_data;
	}
}
