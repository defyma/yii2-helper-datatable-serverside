
# Yii2 Datatable Manual Serverside

This helper only use SQL Query createCommand Not Activerecord!

# How To Use

1. Clone Or Download this project, and extract
2. Copy **DatatableHelper.php** folder to **@app\components**

## Example
controller/site.php

```
	...
    function actioGetdata()
    {
    	if(Yii::$app->request->isAjax)
        {
            $connection = Yii::$app->get('db');
            $sql = "
                SELECT
                    a.id,
                    a.nama,
                    a.alamat
                FROM
                    mahasiswa a
            ";

            $data = \app\components\DatatableHelper::generate([
                'connection' => $connection,
                'query'      => $sql,
                'column' => [
                    'a.id',
                    'a.nama',
                    'a.alamat'
                ],
                'query_has_where' => true,
                'order' => 'a.id ASC'
            ]);

            //Assign Position
            $row = [];
            foreach($data['data'] as $ket => $val)
            {
                $val = [];
                $val = $value['id']; // Col 0
                $val = $value['nama']; // Col 1
                $val = $value['alamat']; // Col 2

                array_push($row, $val);
            }

            //Replace data
            $data['data'] = $row;

            // Return Json data table
    	    \Yii::$app->response->format = Response::FORMAT_JSON;
    	    return $data;
        }

    	$this->render('show_data_mahasiswa', []);
    }
	...
```
----
views/site/show_data_mahasiswa.php
```
<?php
	...
    echo \app\components\DatatableHelper::table([
        'context' => $this,
        'url'     => \Yii::$app->getUrlManager()->createUrl("/site/getdata"),
        'option_datatable' => [
            'ordering' => false,
        ],
        'option'  => [
            'id'       => 'tbl_mahasiswa',
            'class'    => 'table table-hover',
        ],
        'column_label' => [
            'ID', //Header Col 0
            'NAMA', //Header Col 1
            'ALAMAT' //Header Col 2
        ]
    ]);
	...
?>
```
