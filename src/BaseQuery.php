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
    public $data_pagination = null;
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
        $data_pagination = new \stdClass;
        $data_pagination->total = $sum_data;
        $data_pagination->per_page = $limit;
        $data_pagination->offset = $offset;
        $data_pagination->current_page = $currentPage;
        $data_pagination->last_page = $last_page;
        $data_pagination->next_page_url = $currentPage == $last_page ? null : request()->url()."?page=".$next_page;
        $data_pagination->prev_page_url = $currentPage == 1 ? null : request()->url()."?page=".$prev_page;
        $data_pagination->from = $from;
        $data_pagination->to = $to;
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
        $data_pagination->data = $this->data;
        // $this->data_pagination = $data_pagination;
        // $data_pagination->render = $this->render($data_pagination);
        $data_pagination->render = $this->getPaginationString($data_pagination->current_page, $data_pagination->total, $data_pagination->per_page, 1, "");

        return $data_pagination;
    }

    private function render($data_pagination)
    {
        $paginate = '<ul class="pagination">';
        if ($data_pagination->prev_page_url) {
            $paginate .= '<li><a href="'.$data_pagination->prev_page_url.'">&laquo;</a></li>';
        } else {
            $paginate .= '<li><a href="#">&laquo;</a></li>';
        }
        foreach (range(1,intval($data_pagination->last_page)) as $page) {
           $paginate .='<li><a href="?page='.$page.'">'.$page.'</a></li>';
        }

        if ($data_pagination->next_page_url) {
            $paginate .= '<li><a href="'.$data_pagination->next_page_url.'">&raquo;</a></li>';
        } else {
            $paginate .= '<li><a href="#">&raquo;</a></li>';
        }

        
        $paginate .= '</ul>';
        return $paginate;
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
        $insert_data['created_at'] = date('Y-m-d H:i:s');
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
        $insert_data['updated_at'] = date('Y-m-d H:i:s');

        app('db')->table($this->table)
            ->where($this->default_key, $id)
            ->update($insert_data);
        $this->value_default_key = $id;
        $this->execute();
        return current($this->data);
    }

    public function delete($id)
    {
        if ($this->soft_delete) {
            app('db')->table($this->table)
            ->where($this->default_key, $id)
            ->update(["deleted_at" => date('Y-m-d H:i:s')]);
        } else {
            app('db')->table($this->table)->where($this->default_key, $id)->delete();
        }
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

    private function getPaginationString($page = 1, $totalitems, $limit = 15, $adjacents = 1, $targetpage = "/", $pagestring = "?page=", $margin = "10px", $padding = "10px")
    {       
    //defaults
    if(!$adjacents) $adjacents = 1;
    if(!$limit) $limit = 15;
    if(!$page) $page = 1;
    // if(!$targetpage) $targetpage = "/";
    
    //other vars
    $prev = $page - 1;                                  //previous page is page - 1
    $next = $page + 1;                                  //next page is page + 1
    $lastpage = ceil($totalitems / $limit);             //lastpage is = total items / items per page, rounded up.
    $lpm1 = $lastpage - 1;                              //last page minus 1
    
    /* 
        Now we apply our rules and draw the pagination object. 
        We're actually saving the code to a variable in case we want to draw it more than once.
    */
    $pagination = "";
        if($lastpage > 1)
        {   
            $pagination .= "<div class=\"pagination\"";
            if($margin || $padding)
            {
                $pagination .= " style=\"";
                if($margin)
                    $pagination .= "margin: $margin;";
                if($padding)
                    $pagination .= "padding: $padding;";
                $pagination .= "\"";
            }
            $pagination .= ">";

            //previous button
            if ($page > 1) 
                $pagination .= "<a href=\"$targetpage$pagestring$prev\">&laquo; prev</a>";
            else
                $pagination .= "<span class=\"disabled\">&laquo; prev</span>";    
            
            //pages 
            if ($lastpage < 7 + ($adjacents * 2))   //not enough pages to bother breaking it up
            {   
                for ($counter = 1; $counter <= $lastpage; $counter++)
                {
                    if ($counter == $page)
                        $pagination .= "<span class=\"current\">$counter</span>";
                    else
                        $pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";                 
                }
            }
            elseif($lastpage >= 7 + ($adjacents * 2))   //enough pages to hide some
            {
                //close to beginning; only hide later pages
                if($page < 1 + ($adjacents * 3))        
                {
                    for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
                    {
                        if ($counter == $page)
                            $pagination .= "<span class=\"current\">$counter</span>";
                        else
                            $pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";                 
                    }
                    $pagination .= "<span class=\"elipses\">...</span>";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . $lpm1 . "\">$lpm1</a>";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . $lastpage . "\">$lastpage</a>";       
                }
                //in middle; hide some front and some back
                elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
                {
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "1\">1</a>";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "2\">2</a>";
                    $pagination .= "<span class=\"elipses\">...</span>";
                    for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
                    {
                        if ($counter == $page)
                            $pagination .= "<span class=\"current\">$counter</span>";
                        else
                            $pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";                 
                    }
                    $pagination .= "...";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . $lpm1 . "\">$lpm1</a>";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . $lastpage . "\">$lastpage</a>";       
                }
                //close to end; only hide early pages
                else
                {
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "1\">1</a>";
                    $pagination .= "<a href=\"" . $targetpage . $pagestring . "2\">2</a>";
                    $pagination .= "<span class=\"elipses\">...</span>";
                    for ($counter = $lastpage - (1 + ($adjacents * 3)); $counter <= $lastpage; $counter++)
                    {
                        if ($counter == $page)
                            $pagination .= "<span class=\"current\">$counter</span>";
                        else
                            $pagination .= "<a href=\"" . $targetpage . $pagestring . $counter . "\">$counter</a>";                 
                    }
                }
            }
            
            //next button
            if ($page < $counter - 1) 
                $pagination .= "<a href=\"" . $targetpage . $pagestring . $next . "\">next &raquo;</a>";
            else
                $pagination .= "<span class=\"disabled\">next &raquo;</span>";
            $pagination .= "</div>\n";
        }
    
        return $pagination;

    }
}
