<?php
/* @var $this BaseManageController */
/* @var $model Books */

$this->breadcrumbs=array(
	'مدیریت',
);
$this->menu=array(
	array('label'=>'افزودن کتاب', 'url'=>Yii::app()->createUrl('/manageBooks/baseManage/create')),
);
?>

<h1>مدیریت کتاب ها</h1>

<?php $this->widget('zii.widgets.grid.CGridView', array(
	'id'=>'books-grid',
	'dataProvider'=>$model->search(),
	'filter'=>$model,
	'columns'=>array(
		'title',
		array(
			'header' => 'ناشر',
			'value' => '$data->publisher_id?$data->publisher->userDetails->publisher_id:$data->publisher_name',
			'filter' => CHtml::activeTextField($model,'devFilter')
		),
		array(
			'name' => 'category_id',
			'value' => '$data->category->fullTitle',
			'filter' => CHtml::activeDropDownList($model,'category_id',BookCategories::model()->sortList(),array('prompt' => 'همه'))
		),
		array(
			'name' => 'status',
			'value' => '$data->statusLabels[$data->status]',
			'filter' => CHtml::activeDropDownList($model,'status',$model->statusLabels,array('prompt' => 'همه'))
		),
		array(
			'name' => 'lastPackage.price',
			'value' => '$data->price != 0?$data->price:"رایگان"'
		),
		/*
		'file_name',
		'icon',
		'description',
		'change_log',
		'permissions',
		'size',
		'version',
		'confirm',
		*/
		array(
			'class'=>'CButtonColumn',
            'buttons' => array(
                'update' => array(
                    'url' => 'Yii::app()->createUrl("/manageBooks/baseManage/update", array("id"=>$data->id))'
                ),
                'delete' => array(
                    'url' => 'Yii::app()->createUrl("/manageBooks/baseManage/delete", array("id"=>$data->id))'
                ),
                'view' => array(
					'url'=>'Yii::app()->createUrl("/book/".$data->id."/".urlencode($data->title))',
					'options'=>array(
						'target'=>'_blank'
					),
                )
            )
		),
	),
)); ?>