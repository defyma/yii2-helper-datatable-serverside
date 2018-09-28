<?php namespace defyma\helper;

/**
 * DatatableHelper.php
 *
 * @Author: Defy M Aminuddin <defyma> <http://defyma.com>
 * @Email:  defyma85@gmail.com
 * @Filename: DatatableHelper.php
 */

// use yii\base\Component;

class DatatableHelper// extends Component
{
    private static $draw  = 1;
    private static $start = 0;
    private static $limit = 10;
    private static $q     = "";
    private static $query = "";
    private static $columnSearch  = array();
    private static $columnHeader  = array();
    private static $queryHasWhere = false;
    private static $connection    = null;
    private static $order         = "";
    private static $order_column  = "";
    private static $order_dir     = "ASC";
    private static $var_datatable = "tdtdefytbl";
    private static $action        = "";
    private static $show_sql      = false;
    private static $db_type       = 'mysql';

    public static function generate($option)
    {
        //Set Option
        self::setVar($option);

        //Generate SQL
        $sql = self::generate_sql();

        //execute
        $command   = self::$connection->createCommand($sql);
        if(!empty(self::$q))
            $command->bindValue(':qdttbl', "%".strtolower(self::$q)."%");
        $result    = $command->queryAll();

        //Order By Column By Header & assing custom value & action column
        $row = [];
        foreach ($result as $key => $value)
        {
            $val = [];
            foreach (self::$columnHeader as $key2 => $value2) {
                if(is_array($value2)) {
                    $isVal = call_user_func($value2['value'], $value);
                    $val[] = $isVal;
                } else {
                    $val[] = $value[$value2];
                }
            }
            if(!empty(self::$action)) {
                $template = self::$action['template'];
                preg_match_all("/{(.*?)\}/", $template, $arr_tempplate);
                foreach ($arr_tempplate[0] as $key_template => $tmpl)
                {
                    if(!isset(self::$action['buttons'][$arr_tempplate[1][$key_template]]))
                        throw new \yii\web\HttpException(403, "Button ".$tmpl . " Not Defined");

                    $html_aksi = call_user_func(self::$action['buttons'][$arr_tempplate[1][$key_template]], $value);
                    $template  = str_replace($tmpl,$html_aksi,$template);
                }

                $val[] = $template;
            }

            array_push($row, $val);
        }

        //Total All Record
        $totalRecord = self::getTotalRecord();
        $totalSearch = $totalRecord;
        if(!empty(self::$q))
            $totalSearch = self::getTotalSearch();

        //return value
        $data = [
            'draw'            => self::$draw,
            'recordsTotal'    => $totalRecord,
            'recordsFiltered' => $totalSearch,
            'data'            => $row,
            'raw_sql'         => (self::$show_sql) ? $command->rawSql : ""
        ];

        return $data;
    }

    private static function generate_sql()
    {
        if(self::$db_type == "mysql")
            return self::generate_sql_mysql();

        if(self::$db_type == "oracle")
            return self::generate_sql_oracle();

        //add for other db
    }

    private static function generate_sql_oracle()
    {
        $sql = self::$query;
        if(!empty(self::$q)) {
            $sql_seach = self::generateSearch();
            $sql       = str_replace('{{DATATABLE_SEARCH}}',$sql_seach,$sql);
        } else {
            $sql = str_replace('{{DATATABLE_SEARCH}}','',$sql);
        }
        $sql .= self::order();

        $min = (self::$start == 0) ? self::$limit :  (self::$start + self::$limit);
        $max = (self::$start == 0 ) ? self::$start : ($min - self::$limit);

        $newSQL = '
            SELECT * FROM (
	           SELECT "DEFY_TABLE".*, ROWNUM "DEFY_ROWNUM"  FROM (

                   {{SQL}}

               ) "DEFY_TABLE" WHERE ROWNUM <= '.$min.'
            ) WHERE "DEFY_ROWNUM" > '.$max.'
        ';

        $sql = str_replace('{{SQL}}',$sql,$newSQL);

        return $sql;
    }

    private static function generate_sql_mysql()
    {
        $sql = self::$query;
        if(!empty(self::$q)) {
            $sql_seach = self::generateSearch();
            $sql       = str_replace('{{DATATABLE_SEARCH}}',$sql_seach,$sql);
        } else {
            $sql = str_replace('{{DATATABLE_SEARCH}}','',$sql);
        }
        $sql .= self::order();
        $sql .= self::limit();

        return $sql;
    }

    private static function limit()
    {
        return " LIMIT ".self::$start.",".self::$limit;

    }

    private static function order()
    {
        if(self::$order_column != "")
            return " ORDER BY ".self::$columnSearch[self::$order_column]." ".self::$order_dir." ";

        if(self::$order != "")
            return " ORDER BY ".self::$order." ";


        return "";
    }

    private static function generateSearch()
    {
        if(self::$queryHasWhere) $sql = " and ( ";
        else $sql = " where ( ";

        foreach (self::$columnSearch as $key => $value)
        {
            $sql .= " LOWER (".$value.") like :qdttbl ";

            if($key < count(self::$columnSearch) - 1)
                $sql .= " OR ";
        }

        $sql .= " ) ";

        return $sql;
    }

    private static function getTotalRecord()
    {
        if(self::$db_type == "mysql")
        {
            $sql       = self::$query;
            $posisi    = strpos($sql, "FROM");
            $header    = substr($sql, 0, $posisi);
            $sql_count = " SELECT count(1) JML ";
            $generated = str_replace($header, $sql_count, $sql);
            $generated = str_replace('{{DATATABLE_SEARCH}}','',$generated);
            $command   = self::$connection->createCommand($generated);
            $result    = $command->queryOne();

            if($result)
                return $result['JML'];
        }

        if(self::$db_type == "oracle")
        {
            $sql       = self::$query;
            $generated = str_replace('{{DATATABLE_SEARCH}}','',$sql);
            $command   = self::$connection->createCommand($generated);
            $result    = $command->queryAll();
            return count($result);
        }

        return 0;
    }

    private static function getTotalSearch()
    {
        if(self::$db_type == "mysql")
        {
            $sql       = self::$query;
            $posisi    = strpos($sql, "FROM");
            $header    = substr($sql, 0, $posisi);
            $sql_count = " SELECT count(1) JML ";
            $generated = str_replace($header, $sql_count, $sql);
            if(!empty(self::$q)) {
                $sql_seach = self::generateSearch();
                $generated = str_replace('{{DATATABLE_SEARCH}}',$sql_seach,$generated);
            } else {
                $generated = str_replace('{{DATATABLE_SEARCH}}','',$generated);
            }
            $command   = self::$connection->createCommand($generated);
            if(!empty(self::$q))
                $command->bindValue(':qdttbl', "%".strtolower(self::$q)."%");
            $result    = $command->queryOne();
            if($result)
                return $result['JML'];
        }

        if(self::$db_type == "oracle")
        {
            $sql       = self::$query;
            if(!empty(self::$q)) {
                $sql_seach = self::generateSearch();
                $generated = str_replace('{{DATATABLE_SEARCH}}',$sql_seach,$sql);
            } else {
                $generated = str_replace('{{DATATABLE_SEARCH}}','',$sql);
            }
            $command   = self::$connection->createCommand($generated);
            if(!empty(self::$q))
                $command->bindValue(':qdttbl', "%".strtolower(self::$q)."%");
            $result    = $command->queryAll();
            return count($result);
        }

        return 0;
    }

    private static function setVar($option)
    {
        self::$draw  = isset($_GET['draw']) ? $_GET['draw'] : 1;
        self::$start = isset($_GET['start']) ? $_GET['start'] : 0;
        self::$limit = isset($_GET['length']) ? $_GET['length'] : 10;
        self::$q     = isset($_GET['search']['value']) ? $_GET['search']['value'] : "";
        self::$order_column  = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : "";
        self::$order_dir     = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : "ASC";

        self::$query         = $option['query'];
        self::$columnSearch  = $option['columnSearch'];
        self::$columnHeader  = $option['columnHeader'];
        self::$show_sql      = isset($option['show_sql']) ? $option['show_sql'] : false;
        self::$queryHasWhere = isset($option['query_has_where']) ? $option['query_has_where'] : false;
        self::$order         = isset($option['order']) ? $option['order'] : "";
        self::$connection    = $option['connection'];
        self::$action        = isset($option['action_column']) ? $option['action_column'] : array();
        self::$db_type       = isset($option['db_type']) ? $option['db_type'] : 'mysql';
    }

    public static function table($setting = array())
    {
        $contex           = $setting['context'];
        $url              = $setting['url'];
        $column_label     = $setting['column_label'];
        $option           = isset($setting['option']) ? $setting['option'] : array();
        $option_datatable = isset($setting['option_datatable']) ? $setting['option_datatable'] : array();
        // $class_table = isset($option['class']) ? $option['class'] : 'table table-hover';
        // $style_table = isset($option['style']) ? $option['style'] : 'width:100%';
        $id_table        = isset($option['id']) ? $option['id'] : 'tbl_defy_datatable_'.time();
        $option_modal    = isset($option['modal']) ? $option['modal'] : false;
        $option_footer   = isset($option['footer']) ? $option['footer'] : false;
        $option_autoload = isset($option['autoload']) ? $option['autoload'] : true;

        //set option table
        $op_table = "";
        foreach ($option as $key => $value)
        {
            if($key != "id")
            {
                $op_table .= " ".$key." = '".$value."' ";
            }
        }

        //generate table
        $html = '
            <table id="'.$id_table.'" '.$op_table.' >
                <thead>
                    <tr>
        ';

            foreach ($column_label as $key => $value)
            {
                if(is_array($value))
                {
                    $op = "";
                    foreach ($value['option'] as $key1 => $value1)
                    {
                        $op .= " ".$key1." = '".$value1."' ";
                    }
                    $html .= " <th ".$op." > ".$value['label']." </th> ";
                }
                else
                {
                    $html .= " <th> ".$value." </th> ";
                }
            }
        $html .= '
                    </tr>
                </thead>
        ';
        if($option_footer)
        {
            $html .= "<tfoot><tr>";
            foreach ($column_label as $key => $value)
            {
                $html .= " <th></th> ";
            }
            $html .= "</tr></tfoot>";
        }
        $html .= "</table>";


        //Ordering Option
        $ord = isset($option_datatable['ordering']) ? $option_datatable['ordering'] : true;
        $ordering = 'false';
        if($ord)
            $ordering = 'true';

        //columnDefs
        $columnDefs = isset($option_datatable['columnDefs']) ? $option_datatable['columnDefs'] : "";
        $js_columnDefs = "";
        if(!empty($columnDefs))
            $js_columnDefs = ' "columnDefs": '.$columnDefs.', ';

        //footerCallback
        $footerCallback = isset($option_datatable['footerCallback']) ? $option_datatable['footerCallback'] : "";
        $js_footerCallback = "";
        if(!empty($footerCallback))
            $js_footerCallback = ' "footerCallback": '.$footerCallback.', ';

        //language
        $lang = isset($option_datatable['language']) ? $option_datatable['language'] : "";
        $js_lang = "";
        if(!empty($lang)) {
            $js_lang = '
                "language": {
                    "url": "'.$lang.'"
                },
            ';
        }

        //set Var Datatable
        self::$var_datatable = "tdtdefytbl"."_".time();

        //Generate JS
        $js = '
            /*
                DATATABLE HELPER START
                by Defy Ma <defyma.com> <defyma85@gmail.com>
            */

            var '.self::$var_datatable.' = null;

            $(document).ready(() => {';

                if($option_autoload) {
                    $js .= ' initDataTable_'.$id_table.'("'.$id_table.'"); ';
                }

        $js .= '
            });

            if(typeof initDataTable_'.$id_table.' != "function")
            {
                initDataTable_'.$id_table.' = (tbl_id, param) => {

                    //if Datatable Destroy first
                    if ( $.fn.DataTable.isDataTable( "#" + tbl_id ) )
                        $("#" + tbl_id).DataTable().destroy();

                    '.self::$var_datatable.' = $("#" + tbl_id).DataTable( {
                        "processing": true,
                        "serverSide": true,
                        "ajax": "'.$url.'" + "?" + param,
                        "ordering": '.$ordering.',
                        '.$js_columnDefs.'
                        '.$js_footerCallback.'
                        '.$js_lang.'
                    });
                }
            }

            if(typeof reloadTable != "function")
            {
                reloadTable = (tbl_id, page_reset = false) =>
                {
                    if(page_reset)
                        $("#"+tbl_id).DataTable().ajax.reload();
                    else
                        $("#"+tbl_id).DataTable().ajax.reload(null,false);
                }
            }

            if(typeof reloadTableWithUrl != "function")
            {
                reloadTableWithUrl = (tbl_id, url = "") =>
                {
                    if(url == "") {
                        var url   = $("#"+tbl_id).DataTable().ajax.url();
                        //remove param
                        var fix_url = url.split(\'?\')[0];
                        $("#"+tbl_id).DataTable().ajax.url(fix_url).load();
                    } else {
                        $("#"+tbl_id).DataTable().ajax.url(url).load();
                    }
                }
            }

            if(typeof datatable_filter != "function")
            {
                datatable_filter = (tbl_id, form_id) =>
                {
                    var param = $("#" + form_id).serialize();

                    if ( ! $.fn.DataTable.isDataTable( "#" + tbl_id ) ) {
                        window["initDataTable_" + tbl_id](tbl_id, param);
                    } else {
                        var url   = $("#"+tbl_id).DataTable().ajax.url();
                        //remove param
                        var fix_url = url.split(\'?\')[0];
                        $("#"+tbl_id).DataTable().ajax.url(fix_url + "?" + param).load();
                    }

                }
            }

            /*
            DATATABLE HELPER END
            */


        ';
        $html_js = "";
        if($option_modal){
            $html_js = "
                <script type=\"text/javascript\">
                    ".$js."
                </script>
            ";
        } else {
            $contex->registerJs($js, \yii\web\View::POS_BEGIN);
        }


        return $html.$html_js;
    }

    public static function getVarName($value='')
    {
        return self::$var_datatable;
    }

    // public static function tableReload($setting = array())
    // {
    //     if(isset($setting['url']))
    //         return self::$var_datatable.'.ajax.url("'.$setting['url'].'").load();';
    //
    //     else {
    //         $pagingReset = isset($setting['reset_paging']) ? 'null, false' : "";
    //         return self::$var_datatable.'.ajax.reload( '.$pagingReset.' );';
    //     }
    //
    // }

}
