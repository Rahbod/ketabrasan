<?php
class ApiController extends ApiBaseController
{
    protected $request = null;

    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'RestAccessControl + row, search, find, list, page',
            'RestAuthControl + testAuth, bookmark, bookmarkList, comment, discount, buy',
        );
    }

    public function beforeAction($action)
    {
        $this->request = $this->getRequest();
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    /**
     * Get books in a row
     */
    public function actionRow()
    {
        if (isset($this->request['name'])) {
            $limit = 10;
            if (isset($this->request['limit']))
                $limit = $this->request['limit'];

            Yii::import('rows.models.*');
            Yii::import('users.models.*');
            $row = RowsHomepage::model()->findByAttributes(array('query' => $this->request['name']));
            $books = [];
            /* @var Books[] $books */
            if ($row && $row->status == 1)
                $books = Books::model()->findAll($row->getConstCriteria(Books::model()->getValidBooks(null, 'id DESC', $limit)));

            $list = [];
            foreach ($books as $book)
                $list[] = [
                    'id' => intval($book->id),
                    'title' => $book->title,
                    'icon' => Yii::app()->createAbsoluteUrl('/uploads/books/icons') . '/' . $book->icon,
                    'publisher_name' => $book->publisher_id ? $book->publisher->userDetails->getPublisherName() : $book->publisher_name,
                    'author' => ($person = $book->getPerson('نویسنده')) ? $person[0]->name_family : null,
                    'rate' => floatval($book->rate),
                    'price' => doubleval($book->price),
                    'hasDiscount' => $book->hasDiscount(),
                    'offPrice' => $book->hasDiscount() ? doubleval($book->offPrice) : 0,
                ];

            if ($list)
                $this->_sendResponse(200, CJSON::encode(['status' => true, 'list' => $list]), 'application/json');
            else
                $this->_sendResponse(404, CJSON::encode(['status' => false, 'message' => 'نتیجه ای یافت نشد.']), 'application/json');
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Name variable is required.']), 'application/json');
    }

    public function actionSearch()
    {
        if (isset($this->request['query']) and !empty($term = trim($this->request['query']))) {
            $limit = 10;
            if (isset($this->request['limit']))
                $limit = $this->request['limit'];

            Yii::import('users.models.*');

            $criteria = new CDbCriteria();

            $criteria->with = ['publisher', 'publisher.userDetails', 'persons', 'category'];

            $criteria->addCondition('t.status=:status AND t.confirm=:confirm AND t.deleted=:deleted AND (SELECT COUNT(book_packages.id) FROM ym_book_packages book_packages WHERE book_packages.book_id=t.id) != 0');
            $criteria->params[':status'] = 'enable';
            $criteria->params[':confirm'] = 'accepted';
            $criteria->params[':deleted'] = 0;
            $criteria->order = 't.confirm_date DESC';

            $terms = explode(' ', $term);
            $condition = '
                ((t.title regexp :term) OR
                (userDetails.fa_name regexp :term OR userDetails.nickname regexp :term) OR
                (persons.name_family regexp :term) OR
                (category.title regexp :term))';
            $criteria->params[":term"] = $term;

            foreach ($terms as $key => $term)
                if ($term) {
                    if ($condition)
                        $condition .= " OR (";
                    $condition .= "
                        (t.title regexp :term$key) OR
                        (userDetails.fa_name regexp :term$key OR userDetails.nickname regexp :term$key) OR
                        (persons.name_family regexp :term$key) OR
                        (category.title regexp :term$key))";
                    $criteria->params[":term$key"] = $term;
                }
            $criteria->together = true;

            $criteria->addCondition($condition);
            $criteria->limit = $limit;

            /* @var Books[] $books */
            $books = Books::model()->findAll($criteria);

            $result = [];
            foreach ($books as $book)
                $result[] = [
                    'id' => intval($book->id),
                    'title' => $book->title,
                    'icon' => Yii::app()->createAbsoluteUrl('/uploads/books/icons') . '/' . $book->icon,
                    'publisher_name' => $book->publisher_id ? $book->publisher->userDetails->getPublisherName() : $book->publisher_name,
                    'author' => ($person = $book->getPerson('نویسنده')) ? $person[0]->name_family : null,
                    'rate' => floatval($book->rate),
                    'price' => doubleval($book->price),
                    'hasDiscount' => $book->hasDiscount(),
                    'offPrice' => $book->hasDiscount() ? doubleval($book->offPrice) : 0,
                ];

            if ($result)
                $this->_sendResponse(200, CJSON::encode(['status' => true, 'result' => $result]), 'application/json');
            else
                $this->_sendResponse(404, CJSON::encode(['status' => false, 'message' => 'نتیجه ای یافت نشد.']), 'application/json');
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Query variable is required.']), 'application/json');
    }
    /**
     * Get a specific model
     */
    public function actionFind()
    {
        if (isset($this->request['entity']) and isset($this->request['id'])) {
            $entity = $this->request['entity'];
            $criteria = new CDbCriteria();

            switch (trim($entity)) {
                case 'Book':
                    $criteria->addCondition('id = :id');
                    $criteria->params[':id'] = $this->request['id'];
                    $criteria->together = true;
                    /* @var Books $record */
                    $record = Books::model()->find($criteria);

                    if (!$record)
                        $this->_sendResponse(200, CJSON::encode(['status' => false, 'message' => 'نتیجه ای یافت نشد.']), 'application/json');

                    Yii::import('users.models.*');
                    Yii::import('comments.models.*');

                    // Get comments
                    $criteria = new CDbCriteria;
                    $criteria->compare('owner_name', 'Books');
                    $criteria->compare('owner_id', $record->id);
                    $criteria->compare('t.status', Comment::STATUS_APPROWED);
                    $criteria->order = 'parent_comment_id, create_time ';
                    $criteria->order .= 'DESC';
                    $criteria->with = 'user';
                    /* @var Comment[] $commentsList */
                    $commentsList = Comment::model()->findAll($criteria);

                    $comments = [];
                    foreach ($commentsList as $comment)
                        $comments[] = [
                            'id' => intval($comment->comment_id),
                            'text' => $comment->comment_text,
                            'username' => $comment->userName,
                            'rate' => $comment->userRate ? floatval($comment->userRate) : -1,
                            'createTime' => doubleval($comment->create_time),
                        ];

                    // Get similar books
                    $criteria = Books::model()->getValidBooks(array($record->category_id));
                    $criteria->addCondition('id!=:id');
                    $criteria->params[':id'] = $record->id;
                    $criteria->limit = 10;
                    /* @var Books[] $similarBooks */
                    $similarBooks = Books::model()->findAll($criteria);

                    $similar = [];
                    foreach ($similarBooks as $book)
                        $similar[] = [
                            'id' => intval($book->id),
                            'title' => $book->title,
                            'icon' => Yii::app()->createAbsoluteUrl('/uploads/books/icons') . '/' . $book->icon,
                            'publisher_name' => $book->publisher_id ? $book->publisher->userDetails->getPublisherName() : $book->publisher_name,
                            'author' => ($person = $book->getPerson('نویسنده')) ? $person[0]->name_family : null,
                            'rate' => floatval($book->rate),
                            'price' => doubleval($book->price),
                            'hasDiscount' => $book->hasDiscount(),
                            'offPrice' => $book->hasDiscount() ? doubleval($book->offPrice) : 0,
                        ];

                    $book = [
                        'id' => intval($record->id),
                        'title' => $record->title,
                        'icon' => Yii::app()->createAbsoluteUrl('/uploads/books/icons') . '/' . $record->icon,
                        'publisher_name' => $record->publisher_id ? $record->publisher->userDetails->getPublisherName() : $record->publisher_name,
                        'author' => ($person = $record->getPerson('نویسنده')) ? $person[0]->name_family : null,
                        'rate' => floatval($record->rate),
                        'price' => doubleval($record->price),
                        'hasDiscount' => $record->hasDiscount(),
                        'offPrice' => $record->hasDiscount() ? doubleval($record->offPrice) : 0,
                        'description' => strip_tags(str_replace('<br/>', '\n', str_replace('<br>', '\n', $record->description))),
                        'seen' => intval($record->seen),
                        'pagesCount' => intval($record->number_of_pages),
                        'category' => $record->category->title,
                        'comments' => $comments,
                        'similar' => $similar,
                    ];

                    if ($record->preview_file) {
                        $book['previewFile'] = Yii::app()->createAbsoluteUrl('/uploads/books/previews') . '/' . $record->preview_file;
                        $book['previewFileType'] = pathinfo($record->preview_file, PATHINFO_EXTENSION);
                    }

                    break;
                default:
                    $book = null;
                    break;
            }

            if ($book)
                $this->_sendResponse(200, CJSON::encode(['status' => true, 'book' => $book]), 'application/json');
            else
                $this->_sendResponse(404, CJSON::encode(['status' => false, 'message' => 'نتیجه ای یافت نشد.']), 'application/json');
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Entity and ID variables is required.']), 'application/json');
    }
    /**
     * Get list of models
     */
    public function actionList()
    {
        if (isset($this->request['entity']) && $entity = $this->request['entity']) {
            $criteria = new CDbCriteria();
            $criteria->limit = 10;
            $criteria->offset = 0;

            // set LIMIT and OFFSET in Query
            if (isset($this->request['limit']) && !empty($this->request['limit']) && $limit = (int)$this->request['limit']) {
                $criteria->limit = $limit;
                if (isset($this->request['offset']) && !empty($this->request['offset']) && $offset = (int)$this->request['offset'])
                    $criteria->offset = $offset;
            }

            // Execute query on model
            $list = [];
            switch (trim($entity)) {
                case 'Category':
                    /* @var BookCategories[] $categories */
                    $categories = BookCategories::model()->findAll($criteria);

                    foreach ($categories as $category)
                        $list[] = [
                            'id' => intval($category->id),
                            'title' => $category->title,
                            'parent_id' => intval($category->parent_id),
                            'path' => $category->path
                        ];
                    break;
                case 'Book':
                    $criteria->addCondition('t.status=:status');
                    $criteria->addCondition('confirm=:confirm');
                    $criteria->addCondition('deleted=:deleted');
                    $criteria->addCondition('(SELECT COUNT(book_packages.id) FROM ym_book_packages book_packages WHERE book_packages.book_id=t.id) != 0');
                    $criteria->params[':status'] = 'enable';
                    $criteria->params[':confirm'] = 'accepted';
                    $criteria->params[':deleted'] = 0;
                    $criteria->order = 'confirm_date DESC';

                    if (isset($this->request['category_id'])) {
                        $criteria->addCondition('category_id = :catID');
                        $criteria->params[':catID'] = $this->request['category_id'];
                    }

                    if(isset($this->request['id_list']))
                        $criteria->addInCondition('id', $this->request['id_list']);

                    /* @var Books[] $books */
                    $books = Books::model()->findAll($criteria);

                    foreach ($books as $book)
                        $list[] = [
                            'id' => intval($book->id),
                            'title' => $book->title,
                            'icon' => Yii::app()->createAbsoluteUrl('/uploads/books/icons') . '/' . $book->icon,
                            'author' => ($person = $book->getPerson('نویسنده')) ? $person[0]->name_family : null,
                        ];
                    break;
            }

            if ($list)
                $this->_sendResponse(200, CJSON::encode(['status' => true, 'list' => $list]), 'application/json');
            else
                $this->_sendResponse(404, CJSON::encode(['status' => false, 'message' => 'نتیجه ای یافت نشد.']), 'application/json');
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Entity variable is required.']), 'application/json');
    }

    public function actionPage()
    {
        if (isset($this->request['name'])) {
            $text = null;
            Yii::import('pages.models.*');
            switch ($this->request['name']) {
                case "about":
                    $text = Pages::model()->findByPk(10)->summary;
                    break;

                case "help":
                    $text = Pages::model()->findByPk(11)->summary;
                    break;

                case "contact":
                    $text = Pages::model()->findByPk(12)->summary;
                    break;
            }

            if ($text)
                $this->_sendResponse(200, CJSON::encode(['status' => true, 'text' => $text]), 'application/json');
            else
                $this->_sendResponse(404, CJSON::encode(['status' => false, 'message' => 'نتیجه ای یافت نشد.']), 'application/json');
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Name variable is required.']), 'application/json');
    }

    public function actionBookmark()
    {
        if (isset($this->request['book_id'])) {
            $model = UserBookBookmark::model()->find('user_id = :user_id AND book_id = :book_id', array(
                ':user_id' => $this->user->id,
                ':book_id' => $this->request['book_id']
            ));

            if (!$model) {
                $model = new UserBookBookmark();
                $model->book_id = $this->request['book_id'];
                $model->user_id = $this->user->id;
                if ($model->save()) {
                    $book = Books::model()->findByPk($this->request['book_id']);
                    $this->_sendResponse(200, CJSON::encode(['status' => true, 'message' => 'کتاب "' . $book->title . '" با موفقیت نشان شد.']), 'application/json');
                } else
                    $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'در انجام عملیات خطایی رخ داده است!']), 'application/json');
            } else {
                if (UserBookBookmark::model()->deleteAllByAttributes(array('user_id' => $this->user->id, 'book_id' => $this->request['book_id'])))
                    $this->_sendResponse(200, CJSON::encode(['status' => true, 'message' => 'عملیات با موفقیت انجام شد.']), 'application/json');
                else
                    $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'در انجام عملیات خطایی رخ داده است!']), 'application/json');
            }
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Book ID variable is required.']), 'application/json');
    }

    public function actionBookmarkList()
    {
        $list = [];
        foreach ($this->user->bookmarkedBooks as $book)
            $list[] = [
                'id' => intval($book->id),
                'title' => $book->title,
                'icon' => Yii::app()->createAbsoluteUrl('/uploads/books/icons') . '/' . $book->icon,
                'author' => ($person = $book->getPerson('نویسنده')) ? $person[0]->name_family : null,
            ];

        if ($list)
            $this->_sendResponse(200, CJSON::encode(['status' => true, 'list' => $list]), 'application/json');
        else
            $this->_sendResponse(404, CJSON::encode(['status' => false, 'message' => 'نتیجه ای یافت نشد.']), 'application/json');
    }

    public function actionComment()
    {
        if (isset($this->request['book_id']) and isset($this->request['text'])) {
            Yii::import('comments.models.*');
            /* @var Comment $comment */
            $comment = new Comment();
            $comment->owner_name = "Books";
            $comment->owner_id = $this->request['book_id'];
            $comment->creator_id = $this->user->id;
            $comment->comment_text = $this->request['text'];
            $comment->create_time = time();
            $comment->status = Comment::STATUS_NOT_APPROWED;
            $criteria = new CDbCriteria;
            $criteria->compare('owner_name', $comment->owner_name, true);
            $criteria->compare('owner_id', $comment->owner_id);
            $criteria->compare('parent_comment_id', $comment->parent_comment_id);
            $criteria->compare('creator_id', $comment->creator_id);
            $criteria->compare('user_name', $comment->user_name, false);
            $criteria->compare('user_email', $comment->user_email, false);
            $criteria->compare('comment_text', $comment->comment_text, false);
            $criteria->addCondition('create_time>:time');
            $criteria->params[':time'] = time() - 30;
            $model = Comment::model()->find($criteria);
            if ($model)
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'تا 30 ثانیه دیگر امکان ثبت نظر وجود ندارد.']), 'application/json');
            else {
                if ($comment->save()) {
                    if (isset($this->request['rate'])) {
                        $rateModel = BookRatings::model()->findAllByAttributes(array('user_id' => $comment->creator_id, 'book_id' => $comment->owner_id));
                        if ($rateModel)
                            BookRatings::model()->deleteAllByAttributes(array('user_id' => $comment->creator_id, 'book_id' => $comment->owner_id));
                        $rateModel = new BookRatings();
                        $rateModel->book_id = $comment->owner_id;
                        $rateModel->user_id = $comment->creator_id;
                        $rateModel->rate = $this->request['rate'];
                        @$rateModel->save();
                    }

                    $this->_sendResponse(200, CJSON::encode(['status' => true, 'message' => 'نظر شما با موفقیت ثبت شد.']), 'application/json');
                } else
                    $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'در عملیات ثبت خطایی رخ داده است! لطفا مجددا تلاش کنید.']), 'application/json');
            }
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Book ID and Text variables is required.']), 'application/json');
    }

    public function actionDiscount()
    {
        if (isset($this->request['code'])) {
            Yii::app()->getModule('discountCodes');
            $code = $this->request['code'];
            $criteria = DiscountCodes::ValidCodes();
            $criteria->compare('code', $code);
            $discount = DiscountCodes::model()->find($criteria);
            /* @var $discount DiscountCodes */
            if ($discount === NULL)
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'کد تخفیف مورد نظر موجود نیست.']), 'application/json');

            if (!$discount->digital_allow)
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'کد تخفیف مورد نظر مربوط به خرید نسخه چاپی می باشد.']), 'application/json');

            if ($discount->limit_times && $discount->usedCount() >= $discount->limit_times)
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'محدودیت تعداد استفاده از کد تخفیف مورد نظر به اتمام رسیده است.']), 'application/json');

            if ($discount->user_id && $discount->user_id != $this->user->id)
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'کد تخفیف مورد نظر نامعتبر است.']), 'application/json');

            /* @var $used DiscountUsed */
            $used = $discount->codeUsed(array(
                    'condition' => 'user_id = :user_id',
                    'params' => array(':user_id' => $this->user->id),
                )
            );

            if ($used) {
                $u_date = JalaliDate::date('Y/m/d - H:i', $used->date);
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => "کد تخفیف مورد نظر قبلا در تاریخ {$u_date} استفاده شده است."]), 'application/json');
            }

            $this->_sendResponse(200, CJSON::encode(['status' => true, 'discount' => [
                'id' => intval($discount->id),
                'offType' => $discount->off_type == 1 ? 'percent' : 'amount',
                'off' => $discount->off_type == 1 ? floatval($discount->percent) : doubleval($discount->amount)
            ]]), 'application/json');
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Code variable is required.']), 'application/json');
    }

    public function actionBuy()
    {
        if (isset($this->request['book_id']) and isset($this->request['payment_method'])) {
            $userID = $this->user->id;
            $id = $this->request['book_id'];
            /* @var Books $model */
            $model = Books::model()->findByPk($id);

            if (Library::BookExistsInLib($model->id, $model->lastPackage->id, $userID))
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'این کتاب در کتابخانه ی شما موجود است.']), 'application/json');

            // price with publisher discount or not
            $basePrice = $model->hasDiscount() ? $model->offPrice : $model->price;

            $buy = BookBuys::model()->findByAttributes(array('user_id' => $userID, 'book_id' => $id));

            Yii::app()->getModule('users');
            $user = Users::model()->findByPk($userID);
            /* @var $user Users */
            $price = 0;
            if ($model->publisher_id != $userID) {
                Yii::app()->getModule('discountCodes');
                $price = $basePrice; // price, base price with discount code

                if (isset($this->request['discount_code'])) {
                    $discountCodesInSession = DiscountCodes::calculateDiscountCodesManual($price, 'digital', $this->request['discount_code'], $this->user->id);
                    $discountObj = DiscountCodes::model()->findByAttributes(['code' => $discountCodesInSession]);
                } else {
                    $discountCodesInSession = DiscountCodes::calculateDiscountCodesManual($price, 'digital', null, $this->user->id);
                    $discountObj = DiscountCodes::model()->findByAttributes(['code' => $discountCodesInSession]);
                }

                if ($price !== 0) {
                    if ($this->request['payment_method'] == 'credit') {
                        if ($user->userDetails->credit < $price)
                            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'اعتبار فعلی شما کافی نیست!']), 'application/json');

                        $userDetails = UserDetails::model()->findByAttributes(array('user_id' => $userID));
                        $userDetails->setScenario('update-credit');
                        $userDetails->credit = $userDetails->credit - $price;
                        $userDetails->score = $userDetails->score + 1;
                        if ($userDetails->save()) {
                            $buyId = $this->saveBuyInfo($model, $user, 'credit', $basePrice, $price, $discountObj);
                            Library::AddToLib($model->id, $model->lastPackage->id, $user->id);
                            if ($discountCodesInSession)
                                DiscountCodes::InsertCodes($user, $discountObj->getAmount($price)); // insert used discount code in db
                            $this->_sendResponse(200, CJSON::encode(['status' => true, 'message' => 'خرید شما با موفقیت انجام شد.']), 'application/json');
                        } else
                            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'در انجام عملیات خرید خطایی رخ داده است. لطفا مجددا تلاش کنید.']), 'application/json');
                    } elseif ($this->request['payment_method'] == 'gateway') {
                        // Save payment
                        $transaction = new UserTransactions();
                        $transaction->user_id = $userID;
                        $transaction->amount = $price;
                        $transaction->date = time();
                        $transaction->gateway_name = 'زرین پال';
                        $transaction->type = UserTransactions::TRANSACTION_TYPE_BOOK;
                        $transaction->type_id = $model->id;

                        if ($transaction->save()) {
                            $title = $model->title;
                            $gateway = new ZarinPal();
                            $gateway->callback_url = Yii::app()->getBaseUrl(true) . '/book/verify/' . $id . '/' . urlencode($title);
                            $siteName = Yii::app()->name;
                            $description = "خرید کتاب {$title} از وبسایت {$siteName} از طریق درگاه {$gateway->getGatewayName()}";
                            $result = $gateway->request(doubleval($transaction->amount), $description, $this->user->email, $this->user->userDetails && $this->user->userDetails->phone ? $this->user->userDetails->phone : '0');
                            $transaction->scenario = 'set-authority';
                            $transaction->description = $description;
                            $transaction->authority = $result->getAuthority();
                            $transaction->save();
                            //Redirect to URL You can do it also by creating a form
                            if ($result->getStatus() == 100)
                                $this->_sendResponse(200, CJSON::encode(['status' => true, 'url' => $gateway->getRedirectUrl()]), 'application/json');
                            else
                                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'خطای بانکی: ' . $result->getError()]), 'application/json');
                        }
                    }
                } else {
                    $buyId = $this->saveBuyInfo($model, $user, 'credit', $basePrice, $price, $discountObj);
                    Library::AddToLib($model->id, $model->lastPackage->id, $userID);
                    if ($discountCodesInSession)
                        DiscountCodes::InsertCodes($user, $discountObj->getAmount($price)); // insert used discount code in db
                    $this->_sendResponse(200, CJSON::encode(['status' => true, 'message' => 'خرید شما با موفقیت انجام شد.']), 'application/json');
                }
            } else
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'شما ناشر این کتاب هستید. امکان خرید وجود ندارد.']), 'application/json');
        } else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Book ID and Payment Method variables is required.']), 'application/json');
    }

    /**
     * Save buy information
     *
     * @param Books $book
     * @param Users $user
     * @param string $method
     * @param string $price
     * @param string $basePrice
     * @param DiscountCodes$discount
     * @param null $transactionID
     * @return string
     * @throws CException
     */
    private function saveBuyInfo($book , $user ,$method, $basePrice, $price, $discount, $transactionID = null)
    {
        $book->download += 1;
        $book->setScenario('update-download');
        $book->save();
        $buy = new BookBuys();
        $buy->book_id = $book->id;
        $buy->base_price = $basePrice;
        $buy->user_id = $user->id;
        $buy->package_id = $book->lastPackage->id;
        $buy->method = $method;
        $buy->price = $price;
        if($method == 'gateway')
            $buy->rel_id = $transactionID;
        if($book->publisher){
            $book->publisher->userDetails->earning = $book->publisher->userDetails->earning + $book->getPublisherPortion($basePrice, $buy);
            $book->publisher->userDetails->save();
        }
        if($discount && $discount->digital_allow) {
            $buy->discount_code_type = $discount->off_type;
            if ($discount->off_type == DiscountCodes::DISCOUNT_TYPE_PERCENT)
                $buy->discount_code_amount = $discount->percent;
            else if ($discount->off_type == DiscountCodes::DISCOUNT_TYPE_AMOUNT)
                $buy->discount_code_amount = $discount->amount;
        }
        $buy->site_amount = $book->getSitePortion($price, $buy);
        $buy->save();
        $message =
            '<p style="text-align: right;">با سلام<br>کاربر گرامی، جزئیات خرید شما به شرح ذیل می باشد:</p>
            <div style="width: 100%;height: 1px;background: #ccc;margin-bottom: 15px;"></div>
            <table style="font-size: 9pt;text-align: right;">
                <tr>
                    <td style="font-weight: bold;width: 120px;">عنوان کتاب</td>
                    <td>' . CHtml::encode($book->title) . '</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;width: 120px;">قیمت</td>
                    <td>' . Controller::parseNumbers(number_format($price ,0)) . ' تومان</td>
                </tr>';
        if($method == 'gateway' && $buy->transaction)
            $message.= '<tr>
                    <td style="font-weight: bold;width: 120px;">کد رهگیری</td>
                    <td style="font-weight: bold;letter-spacing:4px">' . CHtml::encode($buy->transaction->token) . ' </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;width: 120px;">روش پرداخت</td>
                    <td style="font-weight: bold;">درگاه ' . CHtml::encode($buy->transaction->gateway_name) . ' </td>
                </tr>';
        elseif($method == 'credit')
            $message.= '<tr>
                    <td style="font-weight: bold;width: 120px;">روش پرداخت</td>
                    <td style="font-weight: bold;">کسر از اعتبار</td>
                </tr>';
        $message.= '<tr>
                    <td style="font-weight: bold;width: 120px;">تاریخ</td>
                    <td>' . JalaliDate::date('d F Y - H:i' ,$buy->date) . '</td>
                </tr>
            </table>';
        Mailer::mail($user->email ,'اطلاعات خرید کتاب' ,$message ,Yii::app()->params['noReplyEmail']);
        return $buy->id;
    }

    /** ------------------------------------------------- Authorized Api ------------------------------------------------ **/
    public function actionTestAuth(){
        $this->_sendResponse(200, CJSON::encode(['status' => true, 'message' => 'Access Token works properly.']), 'application/json');  
    }
}