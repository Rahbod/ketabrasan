<?php
/* @var $this PublicController */
/* @var $bookmarked[] UserBookBookmark */
/* @var $book Books */
?>

<div class="transparent-form">
    <h3>نشان شده ها</h3>
    <p class="description">لیست کتاب هایی که نشان کرده اید.</p>

<!--    --><?php //if(empty($model->bookmarkedBooks)):?>
<!--        نتیجه ای یافت نشد.-->
<!--    --><?php //else:?>
<!--        --><?php //foreach($model->bookmarkedBooks as $book):?>
<!--            <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12 bookmarked-book">-->
<!--                <a href="--><?php //echo $this->createUrl('/book/'.$book->id.'/'.urlencode(CHtml::encode($book->title)));?><!--"></a>-->
<!--                <div class="col-lg-5 col-md-5 col-sm-5 col-xs-5 image">-->
<!--                    <img src="--><?php //echo Yii::app()->baseUrl.'/uploads/books/icons/'.CHtml::encode($book->icon);?><!--">-->
<!--                </div>-->
<!--                <div class="col-lg-7 col-md-7 col-sm-7 col-xs-7 info">-->
<!--                    <h5>--><?php //echo CHtml::encode($book->title);?><!--</h5>-->
<!--                    <p class="small">--><?php //echo (is_null($book->publisher_id))?$book->publisher_name:$book->publisher->userDetails->fa_name;?><!--</p>-->
<!--                    <p class="small">--><?php //echo ($book->price==0)?'رایگان':number_format($book->price, 0).' تومان';?><!--</p>-->
<!--                </div>-->
<!--            </div>-->
<!--        --><?php //endforeach;?>
<!--    --><?php //endif;?>



    <?php if(empty($bookmarked)):?>
        نتیجه ای یافت نشد.
    <?php else:?>
        <table class="table">
            <thead>
            <tr>
                <th>عنوان</th>
                <th>نویسنده</th>
                <th>ناشر</th>
                <th>موضوع</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($bookmarked as $book):?>
                <tr>
                    <td><?php echo CHtml::encode($book->title);?></td>
                    <td><?php echo CHtml::encode($book->person);?></td>
                    <td><?php echo CHtml::encode((is_null($book->publisher_id)?$book->publisher_name:$book->publisher->userDetails->fa_name));?></td>
                    <td><?php echo CHtml::encode($book->title);?></td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    <?php endif;?>
</div>