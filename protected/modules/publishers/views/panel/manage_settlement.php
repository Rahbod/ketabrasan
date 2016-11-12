<?php
/* @var $this PanelController*/
/* @var $settlementHistory CActiveDataProvider*/
/* @var $settlementRequiredUsers CActiveDataProvider*/
?>

<?php $this->renderPartial('//layouts/_flashMessage');?>

<h3>کاربرانی که درخواست تسویه حساب دارند</h3>
<?php $this->widget('zii.widgets.grid.CGridView', array(
    'id'=>'required-settlements-grid',
    'dataProvider'=>$settlementRequiredUsers,
    'columns'=>array(
        'fa_name'=>array(
            'name'=>'fa_name',
            'value'=>'CHtml::link($data->user->userDetails->fa_name, Yii::app()->createUrl("/users/manage/views/".$data->user->id))',
            'type'=>'raw'
        ),
        'iban'=>array(
            'name'=>'iban',
            'value'=>'"IR".$data->iban'
        ),
        'amount'=>array(
            'header'=>'مبلغ قابل تسویه',
            'value'=>'number_format($data->getSettlementAmount(), 0)." تومان"'
        ),
        'settled'=>array(
            'value'=>function($data){
                $form=CHtml::beginForm(Yii::app()->createUrl("/publishers/panel/manageSettlement"), 'post', array('class'=>'settlement-form'));
                $form.=CHtml::textField('token', '', array('class'=>'form-control ','placeholder'=>'کد رهگیری'));
                $form.=CHtml::hiddenField('user_id', $data->user_id);
                $form.=CHtml::submitButton('تسویه شد', array('class'=>'btn btn-success'));
                $form.=CHtml::endForm();
                return $form;
            },
            'type'=>'raw'
        ),
    ),
));?>
<?php Yii::app()->clientScript->registerCss('this-page','
.settlement-form .form-control{
    width:200px;
    margin-left:3px;
}
');?>

<h3>تاریخچه تسویه حساب ها</h3>
<?php $this->widget('zii.widgets.grid.CGridView', array(
    'id'=>'settlements-grid',
    'dataProvider'=>$settlementHistory,
    'columns'=>array(
        'date'=>array(
            'name'=>'date',
            'value'=>'JalaliDate::date("d F Y", $data->date)'
        ),
        'amount'=>array(
            'name'=>'amount',
            'value'=>'number_format($data->amount, 0)." تومان"'
        ),
    ),
));?>
