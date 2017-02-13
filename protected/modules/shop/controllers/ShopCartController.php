<?php

class ShopCartController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='//layouts/public';

	/**
	 * @return array actions type list
	 */
	public static function actionsType()
	{
		return array(
			'frontend' => array('view', 'index', 'add', 'remove', 'getPriceTotal'),
		);
	}

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'checkAccess + s',
			'postOnly + add',
		);
	}

	public function actionView()
	{
		Yii::app()->theme="frontend";
		$this->layout="//layouts/index";
		$cart = Shop::getCartContent();

		$this->render('view',array(
			'books'=>$cart
		));
	}

	public function actionGetPriceTotal() {
		echo Shop::getPriceTotal();
	}

	public function actionUpdateAmount()
	{
		$cart = Shop::getCartContent();

		foreach($_GET as $key => $value){
			if(substr($key, 0, 4) == 'qty_'){
				if($value == '')
					return true;
				if(!is_numeric($value) || $value <= 0)
					throw new CException('تعداد نامعتبر است.');
				$position = explode('_', $key);
				$position = $position[1];

				if(isset($cart[$position]['amount']))
					$cart[$position]['amount'] = $value;
				$book = Books::model()->findByPk($position);
				echo $book->getOff_printed_price();
				return Shop::setCartContent($cart);
			}
		}
	}


	public function actionRemove($id){
		$id = (int) $id;
		$cart = json_decode(Yii::app()->user->getState('cart'), true);

		unset($cart[$id]);
		Yii::app()->user->setState('cart', json_encode($cart));

		$this->redirect(array('//shop/cart/view'));
	}

	public function actionAdd(){
		$cart = Shop::getCartContent();
		// remove potential clutter
		if(isset($_POST['yt0']))
			unset($_POST['yt0']);
		if(isset($_POST['yt1']))
			unset($_POST['yt1']);
		$id = $_POST['book_id'];
		if(is_array($cart) && in_array($id,$cart))
		{
			$amount = $cart[$id]['amount'];
			$cart[$id]['amount']+= $amount;
		}else
			$cart[$id] = $_POST;
		Shop::setCartcontent($cart);
		$this->redirect(array('//shop/cart/view'));
	}

	public function actionIndex()
	{
		if(isset($_SESSION['cartowner'])) {
			$carts = ShoppingCart::model()->findAll('cartowner = :cartowner', array(':cartowner' => $_SESSION['cartowner']));

			$this->render('index',array( 'carts'=>$carts,));
		} 
	}

	public function actionAdmin()
	{
		$model=new ShoppingCart('search');
		if(isset($_GET['ShoppingCart']))
			$model->attributes=$_GET['ShoppingCart'];
			$model->cartowner = Yii::app()->User->getState('cartowner');

		$this->render('admin',array(
			'model'=>$model,
		));
	}

	public function loadModel()
	{
		if($this->_model===null)
		{
			if(isset($_GET['id']))
				$this->_model=ShoppingCart::model()->findbyPk($_GET['id']);
			if($this->_model===null)
				throw new CHttpException(404,'The requested page does not exist.');
		}
		return $this->_model;
	}

	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='shopping cart-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
}