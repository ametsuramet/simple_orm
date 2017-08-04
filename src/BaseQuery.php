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
    public $public_function = ['where','limit','deleted_only','deleted','info','orderBy','groupBy','insert','update','delete','get','first','find','last','getQueryLog','paginate'];
    
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


    // [ [id,1], [date, > abg] ]
    // [  ]
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
        // $class_name = get_class($this);
        // $class   = new \ReflectionClass($class_name);
        // $methods = $class->getMethods();

        // $that = $this;
        // $this->functions = $methods;
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

    protected function manyToMany($table,$column,$parent_column,$relation_name = null, $pivot_table)
    {
        if (!$relation_name) {
            $relation_name = $table;
        }
        $table_alias = $table.'_'.$relation_name;
        $this->relation_attributes[$relation_name] = ['name' => $relation_name, 'type' => 'many_to_many', 'alias' => $table_alias, 'pivot_table',$pivot_table];
        $this->manyToMany_attributes[$relation_name] = [$table,$column,$parent_column,$relation_name,$table_alias,$pivot_table];
    }

    protected function hasMany($table,$column,$parent_column,$relation_name = null)
    {
        if (!$relation_name) {
            $relation_name = $table;
        }
        $table_alias = $table.'_'.$relation_name;
        $this->relation_attributes[$relation_name] = ['name' => $relation_name, 'type' => 'many', 'alias' => $table_alias];
        $this->hasMany_attributes[$relation_name] = [$table,$column,$parent_column,$relation_name,$table_alias];
    }

    protected function hasOne($table,$column,$parent_column,$relation_name = null)
    {
        if (!$relation_name) {
            $relation_name = $table;
        }

        $table_alias = $table.'_'.$relation_name;

        $this->relation_attributes[$relation_name] = ['name' => $relation_name, 'type' => 'one', 'alias' => $table_alias];
        $this->hasOne_attributes[$relation_name] = [$table,$column,$parent_column,$relation_name,$table_alias];
    }

    private function getColumn($table,$relation_name = null)
    {
        if (!$relation_name) {
            $relation_name = $table;
        }
        $columns = DB::select("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '".env('DB_DATABASE')."' AND `TABLE_NAME`= '".$table."'");

        $data_colums = array_map(function($data) use ($table,$relation_name) {
                return $relation_name.'.'.$data->COLUMN_NAME. ' as '.$relation_name.'_'.$data->COLUMN_NAME ; 
        }, $columns);
        return $data_colums;
    }

    private function query()
    {
        if ($this->enable_log)
        DB::enableQueryLog();
        $select_params = [];
        // $select_params[] = $this->getColumn($this->table);

        $db = DB::table($this->table);
        foreach ($this->where_params as $key => $where) {
            $db = $db->where($where[0],$where[1],$where[2]);
        }
        // foreach ($this->hasOne_attributes as $key => $rel) {
        //  $db = $db->join($rel[0]." as ".$rel[4],$rel[4].'.'.$rel[1], '=', $this->table.'.'.$rel[2]);
        //  $select_params[] = $this->getColumn($rel[0],$rel[4]);
        // }

        // foreach ($this->hasMany_attributes as $key => $rel) {
        //  $db = $db->join($rel[0],$rel[0].'.'.$rel[1], '=', $this->table.'.'.$rel[2]);
        //  $select_params[] = $this->getColumn($rel[0],$rel[3]);
        // }

        
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
        // $select_params =  call_user_func_array('array_merge', $select_params);
        // $db = $db->select(DB::raw(implode(',',$select_params)));


        if ($this->group_attributes) {
            $db = $db->groupBy(DB::raw($this->group_attributes));
        }


        $db = $db->get();

        $this->data_raw = $db;
        // print_r(array_filter($select_params));
        if ($this->enable_log)
        $this->query_string = (DB::getQueryLog());
    }

    public function paginate($limit)
    {
        $this->execute();

        $searchResults = $this->data;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $collection = new Collection($searchResults);

        $perPage = $limit;

        $currentPageSearchResults = $collection->slice(($currentPage - 1) * $perPage, $perPage)->all();

        $paginatedSearchResults= new LengthAwarePaginator($currentPageSearchResults, count($collection), $perPage);
        $paginatedSearchResults->setPath(request()->url());
        $data_paginate = $paginatedSearchResults->toArray();
        $data_paginate['data'] = array_values($data_paginate['data']);
        return $data_paginate;
    }

    public function insert($data)
    {
        $uuid = \UUID::generate();
        $data['uuid'] = (string)$uuid;
        DB::table($this->table)->insert($data);
        $this->value_default_key = (string)$uuid;
        $this->execute();
        return current($this->data);
    }

    public function update($id,$data)
    {
        DB::table($this->table)
            ->where($this->default_key, $id)
            ->update($data);
        $this->value_default_key = $id;
        $this->execute();
        return current($this->data);
    }

    public function delete($id)
    {
        DB::table($this->table)->where($this->default_key, $id)->delete();
    }

    public function get()
    {
        $this->execute();
        return $this->data;
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

        foreach ($this->data_raw->toArray() as $j => $data_raw) {
            foreach ($data_raw as $k => $value) {
                $data[$j][$k] = $value;
                
                // foreach ($this->relation_attributes as $l => $relation_attribute) {
                //  if ($relation_attribute['type'] == "one") {
                //      $pattern = '/'.$relation_attribute['alias'].'_/';
                //      if (preg_match($pattern,$k)) {
                //          $data[$j][$relation_attribute['alias']][str_replace($relation_attribute['alias'].'_', '', $k)] = $value;
                //      }
                //  } 
                // }

            }

            foreach ($this->relation_attributes as $l => $relation_attribute) {
                if ($relation_attribute['type'] == "many") {
                    foreach ($this->hasMany_attributes as $key => $attribute) {
                        if ($relation_attribute['alias'] == $attribute[4]) {
                            $db = DB::table($attribute[0])->where($attribute[1],$data_raw->{$attribute[2]})->get();
                            $data[$j][$relation_attribute['name']] = $db->toArray();
                        }
                    }
                } 

                if ($relation_attribute['type'] == "one") {
                    foreach ($this->hasOne_attributes as $key => $attribute) {
                        if ($relation_attribute['alias'] == $attribute[4]) {
                            $db = DB::table($attribute[0])->where($attribute[1],$data_raw->{$attribute[2]})->first();
                            $data[$j][$relation_attribute['name']] = (array) $db;
                        }
                    }
                } 

                if ($relation_attribute['type'] == "many_to_many") {
                    foreach ($this->manyToMany_attributes as $key => $attribute) {
                        if ($relation_attribute['alias'] == $attribute[4]) {
                            // print_r($attribute);
                            // print_r($data_raw);
                            $select = $this->getColumn($attribute[0],$attribute[4]);
                            // print_r(implode(',', $select));
                            $db = DB::table($attribute[5][0])->where($attribute[5][1],$data_raw->{$attribute[2]})
                                    ->leftJoin($attribute[0] .' AS '.$attribute[4],$attribute[4].'.'.$attribute[1] ,'=', $attribute[5][0].'.'.$attribute[5][2])
                                    ->select(DB::raw(implode(',', $select)))
                                    ->get();
                            // print_r($db);
                            foreach ($db as $l => $dataManyToMany) {
                                foreach ($dataManyToMany as $m => $value) {
                                    $pattern = '/'.$relation_attribute['alias'].'_/';
                                    if (preg_match($pattern,$m)) {
                                        $data[$j][$attribute[3]][$l][str_replace($relation_attribute['alias'].'_', '', $m)] = $value;
                                    }
                                }
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
