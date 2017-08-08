<?php

namespace Amet\SimpleORM;
use Illuminate\Http\Request;
use DB;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BaseQuery
{
    protected $table = "";
    protected $default_key = "id";
    protected $soft_delete = false;
    private $show_relation = true;
    protected $show_column = [];
    private static $instance;
    private $where_params = [];
    private $data_raw = [];
    public $data = [];
    private $relation_attributes = [];
    private $hasMany_attributes = [];
    private $limit_attributes = [];
    private $hasOne_attributes = [];
    private $manyToMany_attributes = [];
    private $order_attributes = [];
    private $value_default_key = null;
    private $group_attributes = null;
    public $query_string = null;
    public $enable_log = false;
    public $show_deleted = false;
    public $show_deleted_only = false;
    public $public_function = ['where','limit','deleted_only','deleted','info','orderBy','groupBy','insert','update','delete','get','first','find','last','getQueryLog','paginate','set_show_column','hide_relation','count'];
    
    function __construct()
    {
        $class_name = get_class($this);
        $class   = new \ReflectionClass($class_name);
        $methods = $class->getMethods();
        foreach ($methods as $key => $method) {
            if ($method->name != "__construct" && $method->class == $class_name) {
                call_user_func(array($this, $method->name));
            }
        }
        return $this;
    }

    public function set_show_column($value = [])
    {
        $this->show_column = $value;
        return $this;
    }

    public function __get($name)
    {
        return null;
    }

    public function hide_relation()
    {
        $this->show_relation = false;
    }

    public function where($params)
    {
        $wheres = [];
        if (!is_array($params[0])) {
            if (count($params) == 3) {
                $wheres[] = $params;
            } else {
                $wheres[] = [$params[0],"=",$params[1]];
            }

        } else {
            foreach ($params as $key => $param) {

                if (is_array($param)) {
                    if (count($param) == 3) {
                        $wheres[] = $param;
                    } else {
                        $wheres[] = [$param[0],"=",$param[1]];
                    }
                }
            }
        }

        $this->where_params = $wheres;
        return $this;
    }

    public function limit($param1,$param2 = null)
    {
        if ($param2) {
            $this->limit_attributes = [$param1,$param2];    
        } else {
            $this->limit_attributes = [0,$param1];
        }
        return $this;
    }

    public function deleted_only()
    {
        $this->show_deleted_only = true;
        return $this;
    }

    public function deleted($value)
    {
        $this->show_deleted = $value;
        return $this;
    }

    public function info()
    {
        $this->execute();
        return $this;
    }

    public function orderBy($param1,$param2 = 'asc')
    {
        if (is_array($param1)) {
            foreach ($param1 as $key => $order) {
                $this->order_attributes[] = [$order[0],$order[1]];
            }
        } else {
            $this->order_attributes[] = [$param1,$param2];
        }
        return $this;
    }

    public function groupBy($param1)
    {

        if (is_array($param1)) {
            foreach ($param1 as $key => $group) {
                $group_attributes[] = $group;
            }
        } else {
            $group_attributes[] = $param1;
        }

        $this->group_attributes = implode(',', $group_attributes);
        return $this;
    }

    protected function manyToMany($table,$column,$parent_column,$relation_name = null, $pivot_table,$show_column = [])
    {
        if (!$relation_name) {
            $relation_name = $table;
        }
        $table_alias = $table.'_'.$relation_name;
        $this->relation_attributes[$relation_name] = ['name' => $relation_name, 'type' => 'many_to_many', 'alias' => $table_alias, 'pivot_table' => $pivot_table, 'show_column' => $show_column];
        $this->manyToMany_attributes[$relation_name] = [$table,$column,$parent_column,$relation_name,$table_alias,$pivot_table,$show_column];
    }

    protected function hasMany($table,$column,$parent_column,$relation_name = null,$show_column = [])
    {
        if (!$relation_name) {
            $relation_name = $table;
        }
        $table_alias = $table.'_'.$relation_name;
        $this->relation_attributes[$relation_name] = ['name' => $relation_name, 'type' => 'many', 'alias' => $table_alias, 'show_column' => $show_column];
        $this->hasMany_attributes[$relation_name] = [$table,$column,$parent_column,$relation_name,$table_alias,$show_column];
    }

    protected function hasOne($table,$column,$parent_column,$relation_name = null,$show_column = [])
    {
        if (!$relation_name) {
            $relation_name = $table;
        }

        $table_alias = $table.'_'.$relation_name;

        $this->relation_attributes[$relation_name] = ['name' => $relation_name, 'type' => 'one', 'alias' => $table_alias, 'show_column' => $show_column];
        $this->hasOne_attributes[$relation_name] = [$table,$column,$parent_column,$relation_name,$table_alias,$show_column];
    }

    public function getSelectedColumn() 
    {
        $data_colums = $this->show_column;
        if (!count($this->show_column)) {
            $columns = app('db')->select("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '".env('DB_DATABASE')."' AND `TABLE_NAME`= '".$this->table."'");
            $data_colums =  array_map(function($data){
                    return $data->COLUMN_NAME; 
            }, $columns);
        }  
        return $data_colums; 
    }

    public function getAllColumn() 
    {
        $columns = app('db')->select("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '".env('DB_DATABASE')."' AND `TABLE_NAME`= '".$this->table."'");
        $data_colums =  array_map(function($data){
                return $data->COLUMN_NAME; 
        }, $columns);
        return $data_colums; 
    }

    public function getTableName()
    {
        return $this->table;
    }

    public function getDefaultKey()
    {
        return $this->default_key;
    }

    private function getColumn($table,$relation_name = null,$show_column = [])
    {
        if (!$relation_name) {
            $relation_name = $table;
        }
        $columns = app('db')->select("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '".env('DB_DATABASE')."' AND `TABLE_NAME`= '".$table."'");
        
        if (count($show_column)) {
            $columns = collect($columns)->filter(function($data) use ($show_column) {
                if (in_array($data->COLUMN_NAME, $show_column))
                return $data;
            })->toArray();
        }


        $data_colums = array_map(function($data) use ($table,$relation_name) {
                return $relation_name.'.'.$data->COLUMN_NAME. ' as '.$relation_name.'_'.$data->COLUMN_NAME ; 
        }, $columns);
        return $data_colums;
    }

    private function query()
    {
        if ($this->enable_log)
        app('db')->enableQueryLog();
        $select_params = [];

        $db = app('db')->table($this->table);
        foreach ($this->where_params as $key => $where) {
            $db = $db->where($where[0],$where[1],$where[2]);
        }
        
        foreach ($this->order_attributes as $key => $order_attribute) {
            $db = $db->orderBy($order_attribute[0],$order_attribute[1]);
        }

        if (count($this->limit_attributes)) {
            $db = $db->offset($this->limit_attributes[0])->limit($this->limit_attributes[1]);
        }

        if ($this->value_default_key) {
            $db = $db->where($this->default_key,$this->value_default_key)->limit(1);
        }

        if ($this->soft_delete) {
            if ($this->show_deleted_only) {
                    $db = $db->whereNotNull('deleted_at');
            } else {
                if (!$this->show_deleted) {
                    $db = $db->where('deleted_at',null);
                }
            }
        }

        if ($this->group_attributes) {
            $db = $db->groupBy(app('db')->raw($this->group_attributes));
        }

        if (count($this->show_column)) {
            $db = $db->select(app('db')->raw(implode(',', $this->show_column)));
        }


        $db = $db->get();

        $this->data_raw = $db;
        if ($this->enable_log)
        $this->query_string = (app('db')->getQueryLog());
    }

    private function sum_all_data()
    {
        $select_params = [];

        $db = app('db')->table($this->table);
        foreach ($this->where_params as $key => $where) {
            $db = $db->where($where[0],$where[1],$where[2]);
        }
        
        foreach ($this->order_attributes as $key => $order_attribute) {
            $db = $db->orderBy($order_attribute[0],$order_attribute[1]);
        }

        if (count($this->limit_attributes)) {
            $db = $db->offset($this->limit_attributes[0])->limit($this->limit_attributes[1]);
        }

        if ($this->value_default_key) {
            $db = $db->where($this->default_key,$this->value_default_key)->limit(1);
        }

        if ($this->soft_delete) {
            if ($this->show_deleted_only) {
                    $db = $db->whereNotNull('deleted_at');
            } else {
                if (!$this->show_deleted) {
                    $db = $db->where('deleted_at',null);
                }
            }
        }

        if ($this->group_attributes) {
            $db = $db->groupBy(app('db')->raw($this->group_attributes));
        }

            $db = $db->select(app('db')->raw('count(id)'));


        $db = array_values((array) $db->first())[0];
        return $db;
    }

    public function paginate($limit)
    {
        $sum_data = $this->sum_all_data();
        $currentPage = request()->get('page',1);
        if (!$currentPage) {
            $currentPage = 1;
        }

        $last_page = ceil($sum_data/$limit);
        $prev_page = null;
        $next_page = null;
        if ($currentPage !=1) {
            $prev_page = $currentPage - 1;
        }
        if ($currentPage != $last_page) {
            $next_page = $currentPage + 1;
        }

        $from = ($currentPage - 1) * $limit + 1;
        $to = $currentPage == $last_page ? (($currentPage - 1) * $limit)+($sum_data%$limit) : $currentPage * $limit;
        $offset = ($currentPage - 1) * $limit;
        $data_pagination = [
            'total' => $sum_data,
            "per_page" => $limit,
            "offset" => $offset,
            "current_page" => $currentPage,
            "last_page" => $last_page,
            "next_page_url" => $currentPage == $last_page ? null : request()->url()."?page=".$next_page,
            "prev_page_url" => $currentPage == 1 ? null : request()->url()."?page=".$prev_page,
            "from" => $from,
            "to" => $to,
        ];
        $this->limit_attributes = [$offset,$limit];
        $this->execute();

        // $searchResults = $this->data;
        // $currentPage = LengthAwarePaginator::resolveCurrentPage();

        // $collection = new Collection($searchResults);

        // $perPage = $limit;

        // $currentPageSearchResults = $collection->slice(($currentPage - 1) * $perPage, $perPage)->all();

        // $paginatedSearchResults= new LengthAwarePaginator($currentPageSearchResults, count($collection), $perPage);
        // $paginatedSearchResults->setPath(request()->url());
        // $data_paginate = $paginatedSearchResults->toArray();
        $data_pagination['data'] = $this->data;
        return $data_pagination;
    }

    public function count()
    {
        return $this->sum_all_data();
    }

    public function insert($data)
    {
        if (class_exists('\UUID')) {
            $uuid = \UUID::generate();
            $data['uuid'] = (string)$uuid;
        }
        
        $insert_data = [];
        $all_column = $this->getAllColumn();
        foreach ($data as $key => $value) {
            if (in_array($key, $all_column)) {
                $insert_data[$key] = $value;
            }
        }
        app('db')->table($this->table)->insert($insert_data);
        if (class_exists('\UUID')) {
            $this->value_default_key = (string)$uuid;
        }
        $this->execute();
        return current($this->data);
    }

    public function update($id,$data)
    {
        $insert_data = [];
        $all_column = $this->getAllColumn();
        foreach ($data as $key => $value) {
            if (in_array($key, $all_column)) {
                $insert_data[$key] = $value;
            }
        }
        app('db')->table($this->table)
            ->where($this->default_key, $id)
            ->update($insert_data);
        $this->value_default_key = $id;
        $this->execute();
        return current($this->data);
    }

    public function delete($id)
    {
        app('db')->table($this->table)->where($this->default_key, $id)->delete();
    }

    public function get()
    {
        $this->execute();
        return collect($this->data);
    }

    public function getQueryLog()
    {
        $this->enable_log = true;
        $this->execute();
        return $this->query_string;
        
    }

    public function first()
    {
        $this->execute();
        return current($this->data);
    }

    public function find($value_default_key)
    {
        $this->value_default_key = $value_default_key;
        $this->execute();
        return current($this->data);
    }

    public function last()
    {
        $this->execute();
        return end($this->data);
    }

    private function execute()
    {
        $this->query();

        $this->mutation_data();

    }

    private function mutation_data()
    {
        $data = [];
        if ($this->show_relation) {
        }
        foreach ($this->data_raw->toArray() as $j => $data_raw) {
            $data[$j] = new \stdClass;

            foreach ($data_raw as $k => $value) {
                $data[$j]->{$k} = $value;
            }
            if ($this->show_relation) {
                foreach ($this->relation_attributes as $l => $relation_attribute) {
                    if ($relation_attribute['type'] == "one" || $relation_attribute['type'] == "many") {
                        foreach ($this->hasOne_attributes as $key => $attribute) {
                            if ($relation_attribute['alias'] == $attribute[4]) {
                                $db = new $attribute[0];
                                $db = $db->where([$attribute[1],$data_raw->{$attribute[2]}]);
                                if (count($attribute[5])) {
                                    $db = $db->set_show_column($attribute[5]);
                                }
                                $db = $db;
                                $data[$j]->{$relation_attribute['name']} = $db;
                            }
                        }
                        foreach ($this->hasMany_attributes as $key => $attribute) {
                            if ($relation_attribute['alias'] == $attribute[4]) {
                                $db = new $attribute[0];
                                $db = $db->where([$attribute[1],$data_raw->{$attribute[2]}]);
                                if (count($attribute[5])) {
                                    $db = $db->set_show_column($attribute[5]);
                                }
                                $db = $db;
                                $data[$j]->{$relation_attribute['name']} = $db;
                            }
                        }
                    } 

                    if ($relation_attribute['type'] == "many_to_many") {
                        foreach ($this->manyToMany_attributes as $key => $attribute) {
                            if ($relation_attribute['alias'] == $attribute[4]) {
                                $pivot_db = (new $attribute[5][0])->first();
                                
                                $db = new $attribute[0];
                                $db = $db->where([$attribute[1],$pivot_db->{$attribute[5][2]}]);
                                if (count($attribute[6])) {
                                    $db = $db->set_show_column($attribute[6]);
                                }
                                $data[$j]->{$relation_attribute['name']} = $db;
                            }
                        }
                    } 
                }
            }
            
        }
        // dd($data);   
        $this->data = $data;
    }
}
