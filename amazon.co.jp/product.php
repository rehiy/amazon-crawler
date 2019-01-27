<?php

class AmazonProduct
{
    private $ab;
    private $asin;

    private $files = array();

    public function __construct($url)
    {
        $this->ab = new AmazonBridge($url);
        $this->asin = $this->getAsin();

        empty($this->asin) && app_exit(array(
            'error' => '无法获取商品', 'url' => $url
        ));
    }

    public function full()
    {
        return array(
            'asin' => $this->asin,
            'title' => $this->getTitle(),
            'seoMeta' => $this->getSeoMeta(),
            'amazonPrice' => $this->getAmazonPrice(),
            'originalPrice' => $this->getOriginalPrice(),
            'technicalSpecifications' => $this->getTechnicalSpecifications(),
            'importantInformation' => $this->getImportantInformation(),
            'productDescription' => $this->getProductDescription(),
            'aplusFeature' => $this->getAplusFeature(),
            'bulletsFeature' => $this->getBulletsFeature(),
            'bulletsDetail' => $this->getBulletsDetail(),
            'dimensionValues' => $this->getDimensionValues(),
            'mainImages' => $this->getMainImages(),
            'colorImages' => $this->getColorImages(),
            'savedFiles' => $this->files
        );
    }

    /////////////////////////////////////////////////////////////////////////

    //标题
    public function getTitle()
    {
        return $this->ab->xpathQuery('//*[@id="productTitle"]');
    }

    //ASIN
    public function getASIN()
    {
        //模板一
        $res = $this->ab->pregMatch('@ASIN:.+(\w+)</li>@Us');
        //模板二
        if (empty($res)) {
            $res = $this->ab->pregMatch('@ASIN<.+(\w+)</td>@Us');
        }
        return $res ? $res[1] : '';
    }

    //SEO信息
    public function getSeoMeta()
    {
        $title = $this->ab->xpathQuery('/html/head/meta[@name="title"]', 'content');
        $keywords = $this->ab->xpathQuery('/html/head/meta[@name="keywords"]', 'content');
        $description = $this->ab->xpathQuery('/html/head/meta[@name="description"]', 'content');
        $data = preg_replace('@Amazon(\.co\.jp)?[\s:：]*@i', '', compact('title', 'keywords', 'description'));
        return $this->ab->cleanText($data);
    }

    //销售价格
    public function getAmazonPrice()
    {
        $data = $this->ab->xpathQuery('//*[@id="priceblock_ourprice"]');
        return trim(str_replace(',', '', $data), '￥, ');
    }

    //参考价格
    public function getOriginalPrice()
    {
        $data = $this->ab->xpathQuery('//*[@id="price"]/table/tr[1]/td[2]/span[1]');
        return trim(str_replace(',', '', $data), '￥, ');
    }

    //技术指标
    public function getTechnicalSpecifications()
    {
        //模板一
        $res1 = $this->ab->xpathQuery('//*[@id="technicalSpecifications_section_1"]/tr/th', 'text', -1);
        $res2 = $this->ab->xpathQuery('//*[@id="technicalSpecifications_section_1"]/tr/td', 'text', -1);
        //模板二
        if (empty($res1)) {
            $res1 = $this->ab->xpathQuery('(//*[@class="pdTab"])[1]/table/tbody/tr/td[@class="label"]', 'text', -1);
            $res2 = $this->ab->xpathQuery('(//*[@class="pdTab"])[1]/table/tbody/tr/td[@class="value"]', 'text', -1);
        }
        return array_combine($res1, $res2);
    }

    //重要信息
    public function getImportantInformation()
    {
        $data = $this->ab->xpathQuery('//*[@id="importantInformation"]/div/div', 'html');
        return $this->pickImage($data);
    }

    //商品描述
    public function getProductDescription()
    {
        $data = $this->ab->xpathQuery('//*[@id="productDescription"]', 'html');
        return $this->pickImage($data);
    }

    //生产商提供信息
    public function getAplusFeature()
    {
        $data = $this->ab->xpathQuery('//*[@id="aplus_feature_div"]/div/div', 'html');
        return $this->pickImage($data);
    }

    //商品特性
    public function getBulletsFeature()
    {
        $data = $this->ab->xpathQuery('//*[@id="feature-bullets"]/ul/li/span', 'html', -1);
        return $this->pickImage($data);
    }

    //上架信息
    public function getBulletsDetail()
    {
        $data = array();
        //模板一
        $list = $this->ab->xpathQuery('//*[@id="detail_bullets_id"]/table/tr/td/div/ul/li', 'html', -1);
        foreach ((array)$list as $item) {
            if (preg_match('@<b>(.+)[:：\s]*</b>(.+)$@U', $item, $res)) {
                $key = $this->ab->cleanText($res[1]);
                $data[$key] = $this->ab->cleanHtml($res[2]);
            }
        }
        //模板二
        if (empty($data)) {
            $res1 = $this->ab->xpathQuery('(//*[@class="pdTab"])[2]/table/tbody/tr/td[@class="label"]', 'html', -1);
            $res2 = $this->ab->xpathQuery('(//*[@class="pdTab"])[2]/table/tbody/tr/td[@class="value"]', 'html', -1);
            $data = array_combine($res1, $res2);
        }
        return $data;
    }

    //规格参数
    public function getDimensionValues()
    {
        $data = array();
        if ($res = $this->ab->pregMatch('@dimensionValuesData.+(\[.+\]),\n@Us')) {
            $data['data'] = json_decode($res[1], true);
        }
        if ($res = $this->ab->pregMatch('@dimensionValuesDisplayData.+(\{.+\}),\n@Us')) {
            $data['displayData'] = json_decode($res[1], true);
        }
        return $data;
    }

    //主要图片
    public function getMainImages()
    {
        $data = array();
        if ($res = $this->ab->pregMatch('@colorImages.+initial.+(\[.+\])[^\]]+\n@Us')) {
            foreach (json_decode($res[1], true) as $value) {
                $src = $value['hiRes'] ? $value['hiRes'] : $value['large'];
                $data[] = $this->saveImage($src, $this->asin);
            }
        }
        return array_filter(array_unique($data));
    }

    //颜色图片
    public function getColorImages()
    {
        $data = array();
        if ($res = $this->ab->pregMatch('@ImageBlockBTF.+parseJSON\(\'(.+)\'\);\n@Us')) {
            $object = json_decode($res[1], true);
            foreach ($object['colorImages'] as $color => $images) {
                $asin = $object['colorToAsin'][$color]['asin'];
                $data[$asin] = array();
                foreach ($images as $value) {
                    $src = $value['hiRes'] ? $value['hiRes'] : $value['large'];
                    $data[$asin][] = $this->saveImage($src, $asin);
                }
                $data[$asin] = array_filter($data[$asin]);
            }
        }
        return $data;
    }

    /////////////////////////////////////////////////////////////////////////

    private function pickImage($html)
    {
        return preg_replace_callback('@(<img.*\ssrc=[\'"])(http.+)([\'"])@U', function ($res) {
            $src = $this->saveImage($res[2], $this->asin);
            if (strpos($src, 'catalog/product') === 0) {
                $src = '/image/' . $src;
            }
            return $res[1] . $src . $res[3];
        }, $html);
    }

    private function saveImage($url, $asin)
    {
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if (in_array($ext, array('gif', 'png', 'jpg', 'jpeg'))) {
            $path = app_dir('image/' . date('Ym') . '/' . $asin);
            $file = hash('crc32', $url) . '.' . $ext;
            if ($this->ab->getFile($url, $path . $file)) {
                $loc = str_replace(app_dir('image'), 'catalog/product/', $path) . $file;
                $this->files[$loc] = str_replace(app_dir('../..'), '/', $path) . $file;
                return $loc;
            }
        }
        return $url;
    }
}
