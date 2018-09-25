<?php namespace app\components;

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
    private static $queryHasWhere = false;
    private static $connection    = null;
    private static $order         = "";
    private static $order_column  = "";
    private static $order_dir     = "ASC";
    private static $var_datatable = "tdtdefytbl";

    public static function generate($option)
    {
        self::setVar($option);

        $sql = self::$query;

        if(!empty(self::$q))
            $sql .= self::generateSearch();


        $sql .= self::order();

        $sql .= self::limit();

        $command   = self::$connection->createCommand($sql);

        if(!empty(self::$q))
            $command->bindValue(':qdttbl', "%".strtolower(self::$q)."%");

        $result    = $command->queryAll();

        // $row = [];
        // foreach ($result as $key => $value)
        // {
        //     $val = [];
        //     foreach ($value as $key2 => $value2)
        //         $val[] = $value2;
        //
        //     array_push($row, $val);
        // }

        $totalRecord = self::getTotalRecord();

        $data = [
            'draw'            => self::$draw,
            'recordsTotal'    => $totalRecord,
            'recordsFiltered' => !empty($q) ? self::getTotalSearch() : $totalRecord,
            'data'            => $result,
            'raw_sql'         => $command->rawSql
        ];

        return $data;
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
        $sql       = self::$query;
        $posisi    = strpos($sql, "FROM");
        $header    = substr($sql, 0, $posisi);
        // $sql_count = " SELECT count(".self::$columnSearch[0].") jml ";
        $sql_count = " SELECT count(1) jml ";
        $generated = str_replace($header, $sql_count, $sql);
        $command   = self::$connection->createCommand($generated);
        $result    = $command->queryOne();

        if($result)
            return $result['jml'];

        return 0;
    }

    private static function getTotalSearch()
    {
        $sql       = self::$query;
        $posisi    = strpos($sql, "FROM");
        $header    = substr($sql, 0, $posisi);
        // $sql_count = " SELECT count(".self::$columnSearch[0].") jml ";
        $sql_count = " SELECT count(1) jml ";
        $generated = str_replace($header, $sql_count, $sql);

        if(!empty(self::$q))
            $generated .= self::generateSearch();

        $command   = self::$connection->createCommand($generated);

        if(!empty(self::$q))
            $command->bindValue(':qdttbl', "%".strtolower(self::$q)."%");

        $result    = $command->queryOne();

        if($result)
            return $result['jml'];

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
        self::$columnSearch  = $option['column'];
        self::$queryHasWhere = isset($option['query_has_where']) ? $option['query_has_where'] : false;
        self::$order         = isset($option['order']) ? $option['order'] : "";
        self::$connection    = $option['connection'];
    }

    public static function table($setting = array())
    {
        $contex           = $setting['context'];
        $url              = $setting['url'];
        $column_label     = $setting['column_label'];
        $option           = isset($setting['option']) ? $setting['option'] : array();
        $option_datatable = isset($setting['option_datatable']) ? $setting['option_datatable'] : array();

        $id_table    = isset($option['id']) ? $option['id'] : 'tbl_defy_datatable_'.time();
        $class_table = isset($option['class']) ? $option['class'] : 'table table-hover';
        $style_table = isset($option['style']) ? $option['style'] : 'width:100%';

        $html = '
            <table id="'.$id_table.'" class="'.$class_table.'" style="'.$style_table.'">
                <thead>
                    <tr>
        ';

            foreach ($column_label as $key => $value)
            {
                $html .= " <th> ".$value." </th> ";
            }

        $html .= '
                    </tr>
                </thead>
            </table>
        ';

        $ord = isset($option_datatable['ordering']) ? $option_datatable['ordering'] : true;
        $ordering = 'false';
        if($ord)
            $ordering = 'true';

        $pre = "_defy";
        self::$var_datatable = $id_table.$pre;

        $contex->registerJS('
            var '.self::$var_datatable.' = null;

            $(document).ready(() => {
                '.self::$var_datatable.' = $("#'.$id_table.'").DataTable( {
                    "processing": true,
                    "serverSide": true,
                    "ajax": "'.$url.'",
                    "ordering": '.$ordering.',
                });
            });

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
                    if(url == "")
                        $("#"+tbl_id).DataTable().ajax.url("'.$url.'").load();
                    else
                        $("#"+tbl_id).DataTable().ajax.url(url).load();
                }
            }

        ');

        return $html;
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
