<?php

class BaseManageController extends Controller
{
    /**
     * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
     * using two-column layout. See 'protected/views/layouts/column2.php'.
     */
    public $layout = '//layouts/column2';
    public $formats = '.doc ,.docx';

    public function beforeAction($action)
    {
        if(!is_dir(Yii::getPathOfAlias("webroot")."/uploads/books/files/"))
            mkdir(Yii::getPathOfAlias("webroot")."/uploads/books/files/");
        return true;
    }

    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl', // perform access control for CRUD operations
            'postOnly + delete', // we only allow deletion via POST request
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array('allow',  // allow all users to perform 'index' and 'view' actions
                'actions' => array('index', 'view', 'create', 'update', 'admin', 'delete', 'upload', 'deleteUpload', 'uploadFile', 'deleteUploadFile', 'changeConfirm', 'changePackageStatus', 'deletePackage', 'savePackage', 'images', 'download', 'downloadPackage'),
                'roles' => array('admin'),
            ),
            array('deny',  // deny all users
                'users' => array('*'),
            ),
        );
    }

    public function actions(){
        return array(
            'upload' => array(
                'class' => 'ext.dropZoneUploader.actions.AjaxUploadAction',
                'attribute' => 'icon',
                'rename' => 'random',
                'validateOptions' => array(
                    'dimensions' => array(
                        'minWidth' => 512,
                        'minHeight' => 512,
                    ),
                    'acceptedTypes' => array('jpg','jpeg','png')
                )
            ),
            'uploadFile' => array(
                'class' => 'ext.dropZoneUploader.actions.AjaxUploadAction',
                'attribute' => 'file_name',
                'rename' => 'random',
                'validateOptions' => array(
                    'acceptedTypes' => array('doc','docx')
                )
            ),
            'deleteUpload' => array(
                'class' => 'ext.dropZoneUploader.actions.AjaxDeleteUploadedAction',
                'modelName' => 'Books',
                'attribute' => 'icon',
                'uploadDir' => 'uploads/books/icons',
                'storedMode' => 'field'
            ),
            'deleteUploadFile' => array(
                'class' => 'ext.dropZoneUploader.actions.AjaxDeleteUploadedAction',
                'modelName' => 'BookPackages',
                'attribute' => 'file_name',
                'uploadDir' => 'uploads/books/files',
                'storedMode' => 'record'
            )
        );
    }

    /**
     * Displays a particular model.
     * @param integer $id the ID of the model to be displayed
     */
    public function actionView($id)
    {
        $this->render('view', array(
            'model' => $this->loadModel($id),
        ));
    }

    /**
     * Creates a new model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     */
    public function actionCreate()
    {
        $model = new Books();
        $tmpDIR = Yii::getPathOfAlias("webroot") . '/uploads/temp/';
        if (!is_dir($tmpDIR))
            mkdir($tmpDIR);
        $tmpUrl = Yii::app()->createAbsoluteUrl('/uploads/temp/');
        $bookIconsDIR = Yii::getPathOfAlias("webroot") . "/uploads/books/icons/";
        if (!is_dir($bookIconsDIR))
            mkdir($bookIconsDIR);
        $bookIconsThumbDIR = Yii::getPathOfAlias("webroot") . '/uploads/books/icons/150x150/';
        if (!is_dir($bookIconsThumbDIR))
            mkdir($bookIconsThumbDIR);
        $icon = array();

        $this->performAjaxValidation($model);
        if (isset($_POST['Books'])&& file_exists($tmpDIR . $_POST['Books']['icon'])) {
            $model->attributes = $_POST['Books'];
            if (isset($_POST['Books']['icon'])) {
                $file = $_POST['Books']['icon'];
                $icon = array(
                    'name' => $file,
                    'src' => $tmpUrl . '/' . $file,
                    'size' => filesize($tmpDIR . $file),
                    'serverName' => $file,
                );
            }
            $model->confirm='accepted';
            if ($model->save()) {
                if ($model->icon) {
                    $thumbnail = new Imager();
                    $thumbnail->createThumbnail($tmpDIR . $model->icon, 150, 150, false, $bookIconsThumbDIR . $model->icon);
                    @rename($tmpDIR . $model->icon,$bookIconsDIR . $model->icon);
                }
                Yii::app()->user->setFlash('success', 'اطلاعات با موفقیت ثبت شد.');
                $this->redirect('update/' . $model->id . '/?step=2');
            } else
                Yii::app()->user->setFlash('failed', 'در ثبت اطلاعات خطایی رخ داده است! لطفا مجددا تلاش کنید.');
        }

        Yii::app()->getModule('setting');
        $this->render('create', array(
            'model' => $model,
            'icon' => $icon,
            'tax'=>SiteSetting::model()->findByAttributes(array('name'=>'tax'))->value,
            'commission'=>SiteSetting::model()->findByAttributes(array('name'=>'commission'))->value,
        ));
    }

    /**
     * Updates a particular model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id the ID of the model to be updated
     */
    public function actionUpdate($id)
    {
        $tmpDIR = Yii::getPathOfAlias("webroot") . '/uploads/temp/';
        if(!is_dir($tmpDIR)) mkdir($tmpDIR);
        $tmpUrl = Yii::app()->createAbsoluteUrl('/uploads/temp/');
        $bookFilesDIR = Yii::getPathOfAlias("webroot") . "/uploads/books/files/";
        $bookIconsDIR = Yii::getPathOfAlias("webroot") . '/uploads/books/icons/';
        $bookImagesDIR = Yii::getPathOfAlias("webroot") . '/uploads/books/images/';
        $bookFilesUrl = Yii::app()->createAbsoluteUrl("/uploads/books/files");
        $bookIconsUrl = Yii::app()->createAbsoluteUrl('/uploads/books/icons');
        $bookImagesUrl = Yii::app()->createAbsoluteUrl('/uploads/books/images');

        $model = $this->loadModel($id);

        // Uncomment the following line if AJAX validation is needed
        $this->performAjaxValidation($model);
        $icon = array();
        if($model->icon && file_exists($bookIconsDIR . $model->icon))
            $icon = array(
                'name' => $model->icon,
                'src' => $bookIconsUrl . '/' . $model->icon,
                'size' => filesize($bookIconsDIR . $model->icon),
                'serverName' => $model->icon,
            );

        $images = array();
        if($model->images)
            foreach($model->images as $image)
                if(file_exists($bookImagesDIR . $image->image))
                    $images[] = array(
                        'name' => $image->image,
                        'src' => $bookImagesUrl . '/' . $image->image,
                        'size' => filesize($bookImagesDIR . $image->image),
                        'serverName' => $image->image,
                    );
        if(isset($_POST['Books'])) {
            $fileFlag = false;
            $iconFlag = false;
            $newFileSize = $model->size;
            if(isset($_POST['Books']['file_name']) && !empty($_POST['Books']['file_name']) && $_POST['Books']['file_name'] != $model->file_name) {
                $file = $_POST['Books']['file_name'];
                $book = array(
                    'name' => $file,
                    'src' => $tmpUrl . '/' . $file,
                    'size' => filesize($tmpDIR . $file),
                    'serverName' => $file,
                );
                $fileFlag = true;
                $newFileSize = filesize($tmpDIR . $file);
            }
            if(isset($_POST['Books']['icon']) && !empty($_POST['Books']['icon']) && $_POST['Books']['icon'] != $model->icon) {
                $file = $_POST['Books']['icon'];
                $icon = array('name' => $file, 'src' => $tmpUrl . '/' . $file, 'size' => filesize($tmpDIR . $file), 'serverName' => $file,);
                $iconFlag = true;
            }
            $model->attributes = $_POST['Books'];
            $model->size = $newFileSize;
            if($model->save()) {
                if($fileFlag) {
                    rename($tmpDIR . $model->file_name, $bookFilesDIR . $model->file_name);
                }
                if($iconFlag) {
                    $thumbnail = new Imager();
                    $thumbnail->createThumbnail($tmpDIR . $model->icon, 150, 150, false, $bookIconsDIR . $model->icon);
                    unlink($tmpDIR . $model->icon);
                }
                Yii::app()->user->setFlash('success', 'اطلاعات با موفقیت ویرایش شد.');
                $this->refresh();
            } else {
                Yii::app()->user->setFlash('failed', 'در ثبت اطلاعات خطایی رخ داده است! لطفا مجددا تلاش کنید.');
            }
        }

        $criteria=new CDbCriteria();
        $criteria->addCondition('book_id=:book_id');
        $criteria->params=array(
            ':book_id'=>$id,
        );
        $packageDataProvider=new CActiveDataProvider('BookPackages', array('criteria'=>$criteria));

        Yii::app()->getModule('setting');
        $this->render('update', array(
            'model' => $model,
            'icon' => $icon,
            'images' => $images,
            'step' => 1,
            'packageDataProvider'=>$packageDataProvider,
            'tax'=>SiteSetting::model()->findByAttributes(array('name'=>'tax'))->value,
            'commission'=>SiteSetting::model()->findByAttributes(array('name'=>'commission'))->value,
        ));
    }

    /**
     * Deletes a particular model.
     * If deletion is successful, the browser will be redirected to the 'admin' page.
     * @param integer $id the ID of the model to be deleted
     */
    public function actionDelete($id)
    {
        $model = $this->loadModel($id);
        $model->deleted=1;
        $model->setScenario('delete');
        if($model->save())
            $this->createLog('کتاب '.$model->title.' توسط مدیر سیستم حذف شد.', $model->publisher_id);

        // if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
        if (!isset($_GET['ajax']))
            $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
    }

    /**
     * Lists all models.
     */
    public function actionIndex()
    {
        $dataProvider = new CActiveDataProvider('Books');
        $this->render('index', array(
            'dataProvider' => $dataProvider,
        ));
    }

    /**
     * Manages all models.
     */
    public function actionAdmin()
    {
        $model = new Books('search');
        $model->unsetAttributes();
        if (isset($_GET['Books']))
            $model->attributes = $_GET['Books'];
        $this->render('admin', array(
            'model' => $model,
        ));
    }

    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return Books the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model = Books::model()->findByPk($id);
        if ($model === null)
            throw new CHttpException(404, 'The requested page does not exist.');
        return $model;
    }

    /**
     * Performs the AJAX validation.
     * @param Books $model the model to be validated
     */
    protected function performAjaxValidation($model)
    {
        if (isset($_POST['ajax']) && $_POST['ajax'] === 'books-form') {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
    }

    public function actionChangeConfirm()
    {
        $model=$this->loadModel($_POST['book_id']);
        $model->confirm=$_POST['value'];
        if($model->save()) {
            if($_POST['value']=='accepted') {
                $package = BookPackages::model()->find(array('condition' => 'book_id=:book_id', 'params' => array(':book_id' => $model->id), 'order' => 'id DESC'));
                $package->publish_date = time();
                $package->status='accepted';
                $package->setScenario('publish');
                $package->save();
            }
            $message='';
            switch($_POST['value'])
            {
                case 'refused':
                    $message='کتاب '.$model->title.' رد شده است. جهت اطلاع از دلیل تایید نشدن نوبت چاپ جدید به صفحه ویرایش کتاب مراجعه فرمایید.';
                    break;

                case 'accepted':
                    $message='کتاب '.$model->title.' تایید شده است.';
                    break;

                case 'change_required':
                    $message='کتاب '.$model->title.' نیاز به تغییرات دارد. جهت مشاهده پیام کارشناسان به صفحه ویرایش کتاب مراجعه فرمایید.';
                    break;
            }
            $this->createLog($message, $model->publisher_id);
            echo CJSON::encode(array(
                'status' => true
            ));
        }
        else
            echo CJSON::encode(array(
                'status'=>false
            ));
    }

    public function actionChangePackageStatus()
    {
        if (isset($_POST['package_id'])) {
            $model = BookPackages::model()->findByPk($_POST['package_id']);
            $model->status = $_POST['value'];
            $model->setScenario('publish');
            if ($_POST['value'] == 'accepted')
                $model->publish_date = time();
            if ($_POST['value'] == 'refused' or $_POST['value'] == 'change_required')
                $model->reason = $_POST['reason'];
            if ($model->save()) {
                if ($_POST['value'] == 'accepted')
                    $this->createLog('نوبت چاپ ' . $model->package_name . ' توسط مدیر سیستم تایید شد.', $model->book->publisher_id);
                elseif ($_POST['value'] == 'refused')
                    $this->createLog('نوبت چاپ ' . $model->package_name . ' توسط مدیر سیستم رد شد.', $model->book->publisher_id);
                elseif ($_POST['value'] == 'change_required')
                    $this->createLog('نوبت چاپ ' . $model->package_name . ' نیاز به تغییر دارد.', $model->book->publisher_id);
                echo CJSON::encode(array('status' => true));
            } else
                echo CJSON::encode(array('status' => false));
        }
    }

    public function actionDeletePackage($id)
    {
        $model=BookPackages::model()->findByPk($id);
        $uploadDir = Yii::getPathOfAlias("webroot") . '/uploads/books/files';
        if(file_exists($uploadDir.'/'.$model->file_name))
            if(unlink($uploadDir.'/'.$model->file_name))
                if($model->delete())
                    $this->createLog('چاپ ' . $model->package_name . ' توسط مدیر سیستم حذف شد.', $model->book->publisher_id);

        if (!isset($_GET['ajax']))
            $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
    }

    /**
     * Save book package info
     */
    public function actionSavePackage()
    {
        if(isset($_POST['book_id'])) {
            $uploadDir = Yii::getPathOfAlias("webroot") . '/uploads/books/files';
            $tempDir = Yii::getPathOfAlias("webroot") . '/uploads/temp';
            if (!is_dir($uploadDir))
                mkdir($uploadDir);

            $model = new BookPackages();
            $model->book_id = $_POST['book_id'];
            $model->create_date = time();
            $model->publish_date = time();
            $model->status='accepted';
            $model->version = $_POST['version'];
            $model->package_name = $_POST['package_name'];
            $model->price = $_POST['price'];
            $model->printed_price = $_POST['printed_price'];
            $model->file_name = $_POST['Books']['file_name'];
            if ($model->save()) {
                $response = ['status' => true, 'fileName' => $model->file_name];
                @rename($tempDir . DIRECTORY_SEPARATOR . $_POST['Books']['file_name'], $uploadDir . DIRECTORY_SEPARATOR . $model->file_name);
            } else {
                $response = ['status' => false, 'message' => $model->getError('package_name')];
                @unlink($tempDir . '/' . $_POST['Books']['file_name']);
            }

            echo CJSON::encode($response);
            Yii::app()->end();
        }
    }

    public function actionImages($id)
    {
        $tempDir = Yii::getPathOfAlias("webroot") . '/uploads/temp/';
        $uploadDir = Yii::getPathOfAlias("webroot") . '/uploads/books/images/';
        if (isset($_POST['image'])) {
            $flag = true;
            foreach ($_POST['image'] as $image) {
                if (file_exists($tempDir . $image)) {
                    $model = new BookImages();
                    $model->book_id = (int)$id;
                    $model->image = $image;
                    rename($tempDir . $image, $uploadDir . $image);
                    if (!$model->save(false))
                        $flag = false;
                }
            }
            if ($flag)
                Yii::app()->user->setFlash('images-success', 'اطلاعات با موفقیت ثبت شد.');
            else
                Yii::app()->user->setFlash('images-failed', 'در ثبت اطلاعات خطایی رخ داده است! لطفا مجددا تلاش کنید.');
        } else
            Yii::app()->user->setFlash('images-failed', 'تصاویر کتاب را آپلود کنید.');
        $this->redirect('update/' . $id . '/?step=3');
    }

    /**
     * Download book
     */
    public function actionDownload($id)
    {
        $model = $this->loadModel($id);
        $platformFolder = '';
        switch (pathinfo($model->lastPackage->file_name, PATHINFO_EXTENSION)) {
            case 'apk':
                $platformFolder = 'android';
                break;

            case 'ipa':
                $platformFolder = 'ios';
                break;

            case 'xap':
                $platformFolder = 'windowsphone';
                break;
        }
        $this->download($model->lastPackage->file_name, Yii::getPathOfAlias("webroot") . '/uploads/books/files/' . $platformFolder);
    }

    /**
     * Download book package
     */
    public function actionDownloadPackage($id)
    {
        $model = BookPackages::model()->findByPk($id);
        $platformFolder = '';
        switch (pathinfo($model->file_name, PATHINFO_EXTENSION)) {
            case 'apk':
                $platformFolder = 'android';
                break;

            case 'ipa':
                $platformFolder = 'ios';
                break;

            case 'xap':
                $platformFolder = 'windowsphone';
                break;
        }
        $this->download($model->file_name, Yii::getPathOfAlias("webroot") . '/uploads/books/files/' . $platformFolder);
    }

    protected function download($fileName, $filePath)
    {
        $fakeFileName = $fileName;
        $realFileName = $fileName;

        $file = $filePath . DIRECTORY_SEPARATOR . $realFileName;
        $fp = fopen($file, 'rb');

        $mimeType = '';
        switch (pathinfo($fileName, PATHINFO_EXTENSION)) {
            case 'apk':
                $mimeType = 'application/vnd.android.package-archive';
                break;

            case 'xap':
                $mimeType = 'application/x-silverlight-app';
                break;

            case 'ipa':
                $mimeType = 'application/octet-stream';
                break;
        }

        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Transfer-Encoding: binary');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename=' . $fakeFileName);

        echo stream_get_contents($fp);
    }
}
