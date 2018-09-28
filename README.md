
# Yii2 Helper For Datatable Manual Serverside

This helper only use SQL Query createCommand Not Activerecord!

## Install With Composer

```
    php composer.phar require defyma/yii2-datatable-manual-serverside:"v1.*"
```

## Or Require it
```
    "defyma/yii2-datatable-manual-serverside": "v1.*"
```


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
                    a.ID,
                    a.KATEGORI,
                    a.DESIGNATOR,
                    a.URAIAN,
                    a.DESIGNATOR_WBS,
                    a.URAIAN_WBS,
                    a.SATUAN_WBS,
                    sum(a.QTY) QTY,
                    a.SATUAN,
                    sum(a.nilai) NILAI
                FROM
                    EPROP_INDIKATIF_SPEND a
                WHERE
                    ( a.DESIGNATOR IS NOT NULL AND a.ID IS NOT NULL )

                    {{DATATABLE_SEARCH}}

                GROUP BY
                    KATEGORI,
                    DESIGNATOR,
                    URAIAN,
                    satuan,
                    a.DESIGNATOR_WBS,
                    a.URAIAN_WBS,
                    a.SATUAN_WBS
            ";

            $data = \app\components\DatatableHelper::generate([
                'connection' => $connection,
                'db_type'    => 'oracle', //db_type : oracle or mysql, default is mysql
                'query'      => $sql,
                'columnSearch' => [ //Column For Search in Table
                    'a.KATEGORI',
                    'a.DESIGNATOR',
                    'a.URAIAN',
                    'a.SATUAN',
                    'QTY',
                    'NILAI',
                    'a.DESIGNATOR_WBS',
                    'a.URAIAN_WBS',
                    'a.SATUAN_WBS'
                ],
                'columnHeader' => [ //Make Sure 'columnHeader' Same as 'column_label' on View
                    'KATEGORI',
                    'DESIGNATOR',
                    'URAIAN',
                    'SATUAN',
                    [   //Custom Value
                        'column' => 'QTY',
                        'value'  => function($data) {
                            return \app\components\WebHelper::formatNumber($data['QTY']);
                        }
                    ],
                    'NILAI'
                ],
                'query_has_where' => true,
                // 'order'           => 'a.KATEGORI ASC',
                /*
                'action_column' => [           //action_column will generate in last of column
                    'template'  => '{delete} {edit} {some_other_button}',
                    'buttons'   => [
                        'delete' => function($data) {
                            return "<button> This Button Delete ".$data['ID']."</button>";
                        },
                        'edit'  => function($data) {
                            return "<button> This Button Edit ".$data['ID']."</button>";
                        },
                        'some_other_button' => function($data) {
                            return "<button> Other Button </button>";
                        }
                    ]
                ]
                */
            ]);

            //if has footer callback
            $data['footer_total'] = 1000;

            // Return Json data table
    	    \Yii::$app->response->format = Response::FORMAT_JSON;
    	    return $data;
        }

    	return $this->render('show_data_mahasiswa', []);
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
        'url'     => \Yii::$app->getUrlManager()->createUrl(['parameter/spendingitems/getdata']),
        'option_datatable' => [ //Option for Data Table
            'language'   => Yii::$app->params['datatable_indo'],
            'ordering'   => true,
            'columnDefs' => '
                [
                    { className: "dt-right", "targets": [4] },
                    { className: "dt-right", "targets": [5] },
                ]
            ',
            'footerCallback' => '
                function( tfoot, data, start, end, display ) {
                    var response = this.api().ajax.json();
                    if(response){
                        var th = $(tfoot).find("th");
                        th.eq(5).html(response["footer_total"]);
                    }
                }
            '
        ],
        'option'  => [ //Option for Table
            'id'       => 'tbl_indikatif_spending',
            'class'    => 'table table-hover',
            'footer'   => true,
        ],
        'column_label' => [ //Make Sure 'column_label' Same as 'column_header' on Controller
            'Kategori',
            [
                'label' => 'Designator',
                'option' => [ //you can add any option
                    'style' => 'width: 100px'
                ]
            ],
            'Uraian',
            'Satuan',
            'Qty',
            'Nilai',
            'Aksi'  //action_column will generate in last column
        ]
    ]);
    ...
?>
```
