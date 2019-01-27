<?php

class AmazonBridge
{
    private $url;
    private $html;

    private $document;
    private $xpath;

    public function __construct($url)
    {
        $this->url = $url;
        $this->html = $this->getFile($url);

        empty($this->html) && app_exit(array(
            'error' => '远程请求失败', 'url' => $url
        ));

        $this->document = new DOMDocument();
        $this->document->loadHTML($this->html, LIBXML_NOERROR);
        $this->document->normalize();

        $this->xpath = new DOMXPath($this->document);
    }

    public function getFile($url, $save = '')
    {
        if ($save == '') {
            $path = app_dir('bridge/' . date('Ym'));
            $save = $path . hash('crc32', $url);
        }

        if (is_file($save)) {
            return file_get_contents($save);
        }

        $data = $this->httpQuery($url);
        if ($data && file_put_contents($save, $data)) {
            app_log('save.log', "SAV:{$url} -> {$save}");
            return $data;
        }
    }

    public function httpQuery($url, $post = null)
    {
        app_log('http.log', "GET:{$url}");
        $ch = curl_init($url); //创建CURL对
        curl_setopt($ch, CURLOPT_HEADER, 0); //返回头
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //返回信息
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //跟踪重定向
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6); //连接超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); //读取超时时间
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //对认证证书来源的检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'authority: www.amazon.co.jp',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'referer: ' . $this->url != $url ? $this->url : 'https://www.amazon.co.jp',
            'accept-language: zh-CN,zh;q=0.9,en;q=0.8',
            'accept-encoding: gzip, deflate, br',
        ));
        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $res = curl_exec($ch);
        if ($errno = curl_errno($ch)) {
            app_log('http.log', 'ERR:' . curl_error($ch));
        }
        curl_close($ch);
        return $errno ? null : $res;
    }

    public function pregMatch($expr, $all = false)
    {
        $func = $all ? 'preg_match_all' : 'preg_match';
        if ($func($expr, $this->html, $res)) {
            return $res;
        }
    }

    public function xpathQuery($rule, $name = 'text', $idx = 0)
    {
        $nodeList = $this->xpath->query($rule);
        if ($nodeList->length == 0) {
            return $idx == -1 ? array() : '';
        }
        $result = array();
        foreach ($nodeList as $node) {
            switch ($name) {
                case 'text':
                    $value = $node->nodeValue;
                    $value = $this->cleanText($value);
                    break;
                case 'html':
                    $value = $this->document->saveHTML($node);
                    $value = preg_replace('@^<(\w+)[^>]*>(.+)</\1>$@s', '$2', $value);
                    $value = $this->cleanHtml($value);
                    break;
                default:
                    $value = $node->getAttribute($name);
                    $value = $this->cleanText($value);
            }
            $value && $result[] = $value;
        }
        return $idx == -1 ? $result : (isset($result[$idx]) ? $result[$idx] : '');
    }

    public function cleanHtml($html)
    {
        if (is_array($html)) {
            return array_map(array($this, 'cleanHtml'), $html);
        }
        $html = str_replace(pack('CCC', 0xef, 0xbb, 0xbf), '', $html);
        $html = preg_replace_callback('@(\s+([\w-]+)=".*")+@Us', function ($res) {
            return in_array($res[2], array('class', 'style', 'href', 'src')) ? $res[0] : '';
        }, $html);
        $html = preg_replace('@<a.*href=[\'"]javascript.+</a>@Us', '', $html);
        $html = preg_replace('@<a.*(\shref=.+[\'"]).*>@Us', '<a$1>', $html);
        $html = preg_replace('@<(\w+)[\s>]+</\1>@Us', '', $html);
        $html = preg_replace('@<iframe.+/iframe>@Us', '', $html);
        $html = preg_replace('@<script.+/script>@Us', '', $html);
        $html = preg_replace('@<style.+/style>@Us', '', $html);
        $html = preg_replace('@<!--.*-->@Us', '', $html);
        $html = preg_replace('@>\s+<@s', '><', $html);
        $html = preg_replace('@\s+@s', ' ', $html);
        return trim($html);
    }

    public function cleanText($text)
    {
        if (is_array($text)) {
            return array_map(array($this, 'cleanText'), $text);
        }
        $text = str_replace(pack('CCC', 0xef, 0xbb, 0xbf), '', $text);
        $text = preg_replace('@\s+@', ' ', $text);
        return trim(trim($text), '|:： ');
    }
}
