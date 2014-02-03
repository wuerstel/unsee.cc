<?php

class UploadController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $response = new stdClass();
        $upload = new Zend_File_Transfer();

        $ttlTypes = Unsee_Mongo_Document_Hash::$_ttlTypes;
        $ttl = $this->getParam('time', 1);

        if (!in_array($ttl, $ttlTypes)) {
            $ttl = $ttlTypes[1];
        }

        $upload->addValidator('Count', false, array('min' => 1, 'max' => 100));
        $upload->addValidator('IsImage', false);
        //Limit individual file size to 4M, since BSON is MongoDB object is capped to that amount
        $upload->addValidator('Size', false, array('max' => '4MB', 'bytestring' => false));

        if (!$upload->receive()) {
            $translate = Zend_Registry::get('Zend_Translate');
            $response->error = $translate->translate('error_uploading');
        } else {
            $files = $upload->getFileInfo();

            $newHash = (string) new Unsee_Hash();

            $hashDoc = new Unsee_Mongo_Document_Hash();
            $hashDoc->hash = $newHash;
            $hashDoc->timestamp = new MongoDate();
            $hashDoc->ttl = $ttl;
            $hashDoc->views = 0;
            $hashDoc->save();

            foreach ($files as $file => &$info) {
                if (!$upload->isUploaded($file)) {
                    $info = null;
                } else {

                    $imageContent = file_get_contents($info['tmp_name']);
                    $image = new Imagick();
                    $image->readimageblob($imageContent);

                    $imageDoc = new Unsee_Mongo_Document_Image();
                    $imageDoc->hashId = $hashDoc->getId();
                    $imageDoc->data = base64_encode($image);
                    $imageDoc->size = $info['size'];
                    $imageDoc->type = $info['type'];
                    $imageDoc->save();
                }
            }

            $response->hash = $newHash;
        }

        $this->_helper->json->sendJson($response);
    }
}
