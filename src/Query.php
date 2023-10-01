<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait Query
{
    private $dt_query;
    private $query_count;
    private $query_custom_filter;
    private $pagination_data;

    private array|null $allowed_items_per_page = null;
    private bool $is_search_enable = false;
    private bool $is_sort_enable = true;

    protected function query(array $selected_columns)
    {
        return is_callable($this->dt_query) ? ($this->dt_query)($selected_columns) : $this->dt_query;
    }

    protected function queryCount(EloquentBuilder|QueryBuilder $query)
    {
        if ($this->query_count == null) {
            if ($query instanceof EloquentBuilder) {
                if (!empty($query->getQuery()->groups)) return $query->getQuery()->getCountForPagination();
            }

            return $query->count();
        }

        return is_callable($this->query_count) ? ($this->query_count)($query) : $this->query_count;
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

    private function queryOrder(EloquentBuilder|QueryBuilder $query)
    {
        $request = request();

        $frontend_framework = $this->frontend_framework ?? config('sd-datatable-two-ssp.frontend_framework', 'others');

        $arranged_cols_details = $this->getArrangedColsDetails();
        $dt_cols = $arranged_cols_details['dt_cols'];
        $db_cols_mid = $arranged_cols_details['db_cols_mid'];
        $db_cols_final = $arranged_cols_details['db_cols_final'];
        
        $sortable_cols = [];

        foreach ($dt_cols as $index => $dt_col) {
            if ($this->isSortable($dt_col)) $sortable_cols[$index] = $this->filterColName($db_cols_final[$index]);
        }

        if ($frontend_framework == "datatablejs") {
            $request->validate([
                'order' => ['filled', 'array'],
                'order.*.column' => ['required', 'in:' . implode(",", array_keys($sortable_cols))],
                'order.*.dir' => ['required', 'in:asc,desc'],
            ], [
                'order.*.column.in' => 'Order column is invalid. Allowed Order column: ' . implode(",", $sortable_cols),
                'order.*.dir.in' => 'Order dir must be either asc or desc',
            ]);

            if ($request->filled('order')) {
                $query->orderBy($db_cols_mid[$request->order[0]["column"]], $request->order[0]['dir']);
            }

        } else if (in_array($frontend_framework, ["vuetify", "others"])) {

            if ($request->filled('sortBy') && $request->filled('sortDesc')) {
                $request->validate([
                    'sortBy' => ['in:' . implode(",", $sortable_cols)],
                    'sortDesc' => ['in:1,0,true,false'],
                ],[
                    'sortBy.in' => 'Selected sortBy is invalid. Allowed sortBy: ' . implode(",", $sortable_cols),
                    'sortDesc.in' => 'sortDesc must be either 1,0,true or false',
                ]);

                $sortDesc = $request->sortDesc;

                if (is_string($request->sortDesc) || is_numeric($request->sortDesc)) {
                    $sortDesc = filter_var($request->sortDesc, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }

                $col_index = array_flip($this->filterColName($db_cols_final))[$request->sortBy];

                $query->orderBy($this->filterColName($db_cols_mid[$col_index]), ($sortDesc ? 'desc':'asc'));
            }

        }

        return $query;
    }

    private function queryPagination(EloquentBuilder|QueryBuilder $query)
    {
        $request = request();

        $pagination_data = $this->getPaginationData();

        if (isset($pagination_data['items_per_page']) && isset($pagination_data['offset'])) {
            if ($pagination_data['items_per_page'] != "-1") $query->limit($pagination_data['items_per_page'])->offset($pagination_data['offset']);
        }

        return $query;
    }

    private function getPaginationData()
    {
        if ($this->pagination_data !== null) return $this->pagination_data;

        $request = request();

        $ret = [];

        $frontend_framework = $this->frontend_framework ?? config('sd-datatable-two-ssp.frontend_framework', 'others');

        if ($frontend_framework == "datatablejs") {

            $firstRequestName = 'start';
            $secondRequestName = 'length';

            if ($request->filled('length') && $request->filled('start')) {
                $ret['items_per_page'] = $request->length;
                $ret['offset'] = $request->start;
            }

        } else if (in_array($frontend_framework, ["vuetify", "others"])) {

            $firstRequestName = 'page';
            $secondRequestName = 'itemsPerPage';

            if ($request->filled('itemsPerPage') && $request->filled('page')) {
                $ret['items_per_page'] = $request->itemsPerPage;
                $ret['offset'] = ($request->page - 1) * $request->itemsPerPage;
            }

        }

        $validation_rules = [
            $firstRequestName => ['required_with:'.$secondRequestName],
            $secondRequestName => ['required_with:'.$firstRequestName],
        ];

        $validation_error_messages = [];

        if (!empty($this->allowed_items_per_page)) {
            $allowed_items_per_page = $this->getAllowedItemsPerPage();

            if (is_array($allowed_items_per_page)) {
                $allowed_items_per_page = array_map(function($v){ return intval($v); }, $allowed_items_per_page);

                if (!in_array(-1, $allowed_items_per_page)) {
                    array_push($validation_rules[$firstRequestName], 'required');
                    array_push($validation_rules[$secondRequestName], 'required', 'in:' . implode(',', $allowed_items_per_page));
                    $validation_error_messages["$secondRequestName.in"] = "The selected $secondRequestName is invalid. Available options: " . implode(',', $allowed_items_per_page);
                }
            }
        }

        $request->validate($validation_rules, $validation_error_messages);

        $this->pagination_data = $ret;

        return $ret;
    }

    protected function queryCustomFilter(EloquentBuilder|QueryBuilder $query)
    {
        if ($this->query_custom_filter == null) return $query;

        return is_callable($this->query_custom_filter) ? ($this->query_custom_filter)($query) : $this->query_custom_filter;
    }

    public function setQueryCustomFilter(callable|EloquentBuilder|QueryBuilder $query)
    {
        $this->query_custom_filter = $query;

        return $this;
    }

    private function querySearch(EloquentBuilder|QueryBuilder $query)
    {
        $search_value = $this->getSearchValue();

        if (!empty($search_value)) {
            $arranged_cols_details = $this->getArrangedColsDetails();
            $db_cols_initial = $arranged_cols_details['db_cols_initial'];
            $db_cols_mid = $arranged_cols_details['db_cols_mid'];
            $db_cols_final = $arranged_cols_details['db_cols_final'];

            $query = $query->where(function($the_query) use($db_cols_initial, $search_value){
                foreach ($db_cols_initial as $index => $e_col) {
                    if ($index == 0) $the_query->where($e_col, 'LIKE', "%".$search_value."%");
                    else $the_query->orWhere($e_col, 'LIKE', "%".$search_value."%");
                }
            });
        }

        return $query;
    }

    private function getSearchValue(): string
    {
        if (! $this->is_search_enable) return '';

        $request = request();
        $frontend_framework = $this->frontend_framework ?? config('sd-datatable-two-ssp.frontend_framework', 'others');

        $search_value = '';

        if ($frontend_framework == "datatablejs") {

            if ($request->filled('search')) {
                $search_value = $request->search['value'] ?? '';
            }

        } else if (in_array($frontend_framework, ["vuetify", "others"])) {

            if ($request->filled('search')) {
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

    public function disableSorting(bool $disable = true)
    {
        $this->is_sort_enable = !$disable;

        return $this;
    }

    public function setAllowedItemsPerPage(int|array $allowed_items_per_page)
    {
        $this->allowed_items_per_page = is_numeric($allowed_items_per_page) ? [$allowed_items_per_page] : (is_array($allowed_items_per_page) ? $allowed_items_per_page : null);

        return $this;
    }

    public function getAllowedItemsPerPage(): ?array
    {
        return $this->allowed_items_per_page;
    }

    public function isSearchEnabled(): bool
    {
        return $this->is_search_enable;
    }

    public function isSortingEnabled(): bool
    {
        return $this->is_sort_enable;
    }
}
