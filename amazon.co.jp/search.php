<?php

class AmazonSearch
{
    private $kw;
    private $url;
    private $language;

    public function __construct($kw, $language = '')
    {
        $this->kw = $kw;
        $this->url = 'https://www.amazon.co.jp/s/?field-keywords=' . $kw;
        $this->language = '&language=' . ($language ? $language : 'ja_JP');
    }

    public function getProduct()
    {
        $ab = new AmazonBridge($this->url . $this->language);
        $pu = $ab->xpathQuery('//*[@id="result_0"]/div/div/div/a', 'href');

        if (!$pu && strlen($this->kw) == 10) {
            $pu = 'https://www.amazon.co.jp/dp/' . $this->kw;
        }

        return new AmazonProduct(urldecode($pu . $this->language));
    }

    public function showProductData()
    {
        $data = $this->getProduct()->full();
        $data['extra'] = array();

        $temp = array_merge(
            $data['bulletsDetail'],
            $data['bulletsFeature'],
            $data['technicalSpecifications']
        );
        foreach ($temp as $key => $value) {
            $this->getSize($key, $value, $data['extra']);
            $this->getWeight($key, $value, $data['extra']);
            $this->getModel($key, $value, $data['extra']);
        }

        app_exit($data);
    }

    private function getSize($key, $value, &$target)
    {
        if (isset($target['height']) && $target['height'] > 0) {
            return;
        }
        if (preg_match('@([\d\.]+)m*\s*x\s*([\d\.]+)m*\s*x\s*([\d\.]+)\s*mm@Usi', $value, $res)) {
            $target['length'] = $res[1];
            $target['width'] = $res[2];
            $target['height'] = $res[3];
            return;
        }
        if (preg_match('@([\d\.]+)[cm]*\s*x\s*([\d\.]+)[cm]*\s*x\s*([\d\.]+)\s*cm@Usi', $value, $res)) {
            $target['length'] = $res[1] * 10;
            $target['width'] = $res[2] * 10;
            $target['height'] = $res[3] * 10;
            return;
        }
    }

    private function getWeight($key, $value, &$target)
    {
        if (isset($target['weight']) && $target['weight'] > 0) {
            return;
        }
        if (mb_strstr($key, '送重量') !== false && preg_match('@([\d\.]+)\s*(k?g)@Usi', $value, $res)) {
            if (strtolower($res[2]) == 'kg') {
                return $target['weight'] = $res[1] * 1000;
            }
            return $target['weight'] = $res[1];
        }
    }

    private function getModel($key, $value, &$target)
    {
        if (isset($target['model']) && $target['model'] != '') {
            return;
        }
        if (mb_strstr($key, '型号') !== false || mb_strstr($key, '型番') !== false || mb_strstr($key, 'モデル') !== false) {
            return $target['model'] = $value;
        }
    }
}
