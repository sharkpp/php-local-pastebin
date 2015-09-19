<?php
//error_log(print_r($_SERVER,true), 3, "error.log");
    // failback to built-in server
    if (php_sapi_name() != 'cli-server') {
        // boot php built-in server
        $fp = popen('php -S localhost:8080 index.php 2>&1', 'r');
        stream_set_blocking($fp, 0);
        while (!feof($fp)) {
            if (false !== ($read = fread($fp, 10)))
                echo $read;
            else
                usleep (50000); // wait 50ms
        }
        exit;
    }
    // routing
    if ('/' != $_SERVER['SCRIPT_NAME'] &&
        '/'.basename(__FILE__) != $_SERVER['SCRIPT_NAME'])
    {
        // redirect root, if deny url access
        if (!preg_match('!.*(/assets/(cache)/(.+?)/(.+))$!', $_SERVER['SCRIPT_NAME'], $m) &&
            !preg_match('!.*(/assets/(css|js)/(.+?))$!', $_SERVER['SCRIPT_NAME'], $m))
        {
            header('HTTP/1.0 403 Forbidden');
            echo 'forbidden '.$_SERVER['REQUEST_URI'].PHP_EOL;
            exit;
        }
        $is_cdn_url= 4 != count($m);
        $localpath = $m[1];
        $version   = !$is_cdn_url ? ''
                                  : isset($_GET['v']) ? $_GET['v'] : '*';
        $product   = !$is_cdn_url ? '' : $m[3];
        $asset_path= !$is_cdn_url ? '' : $m[4];
        // load from local file
        $basepath= realpath(dirname(__FILE__).'/assets').'/';
        $fname   = realpath(dirname(__FILE__).$localpath);
        if ($basepath == substr($fname, 0, strlen($basepath)) &&
            file_exists($fname)) {
            // return results for web-browser
            return false;
        }
        else if (!$is_cdn_url) {
            header('HTTP/1.0 403 Forbidden');
            echo 'forbidden '.$_SERVER['REQUEST_URI'].PHP_EOL;
            exit;
        }
        // load from cdnjs.com
        //   "assets/cache" / {name} / {version} / {filename}
        $basepath .= 'cache/'.$product.'/';
        // load meta.json
        @ mkdir($basepath, 0777, true);
        $meta_json = @ file_get_contents($basepath.'meta.json');
        if (empty($meta_json)) {
            $meta_json = @ file_get_contents('https://api.cdnjs.com/libraries/'.$product);
            file_put_contents($basepath.'meta.json', $meta_json);
        }
        $meta = json_decode($meta_json, true);
        // search asset file from cdnjs.com
        foreach ($meta['assets'] as $asset) {
            if (!fnmatch($version, $asset['version']))
                continue;
            foreach ($asset['files'] as $file) {
              //echo $file.' , '.$asset_path.PHP_EOL;
                if ($file != $asset_path)
                    continue;
                $cdn_url = 'https://cdnjs.cloudflare.com/ajax/libs/'
                           .$product.'/'.$asset['version'].'/'.$file;
                $cdn_data= @ file_get_contents($cdn_url);
                @ mkdir(dirname($basepath.$file), 0777, true);
                file_put_contents($basepath.$file, $cdn_data);
                foreach ($http_response_header as $header) {
                    header($header);
                }
                echo $cdn_data;
                exit;
            }
        }
        header('HTTP/1.0 404 Not found');
        var_dump($_SERVER);var_dump($version);var_dump($product);
        echo 'Not found '.$_SERVER['REQUEST_URI'].PHP_EOL;
        exit;
    }
    //
    if ($_POST) {
//        ini_set( 'display_errors', '1');
//        ini_set( 'error_reporting', 2147483647);
        // save temporary
        $fname = dirname(__FILE__).'/history/tmp.php';
        @ mkdir(dirname($fname), 0777, true);
        file_put_contents($fname, $_POST['code']);
        // run
        $fp = popen('php "'.$fname.'" 2>&1', 'r');
        while (!feof($fp)) {
            $read = fread($fp, 10);
            echo $read;
        }
        exit;
    }

?><!DOCTYPE html>
<html lang="en">
<head>
    <title>PHP local pastebin</title>
    <link rel="stylesheet" href="assets/cache/twitter-bootstrap/css/bootstrap.min.css?v=3.*">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div id="tab">

<ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"
       ><a aria-controls="code"    role="tab" data-toggle="tab" href="#code"
          >Code</a
   ></li>
    <li role="presentation"
       ><a aria-controls="history" role="tab" data-toggle="tab" href="#history"
          >History</a
   ></li>
</ul>

<div class="tab-content">

<div role="tabpanel" class="tab-pane fade in active" id="code">

    <div id="code_editor">

        <form id="toolbox" class="form-inline" method="post" action="?run">
            <div class="form-group">
                <button type="submit" class="btn btn-primary ">Run</button>
                <input type="text" class="form-control" name="title" placeholder="Title">
            </div>
            <textarea name="editor"></textarea>
        </form>

        <div id="editor"></div>
        
    </div>

    <div id="preview"><?php
    ?></div>

</div>

<div role="tabpanel" class="tab-pane fade" id="history">
</div>

</div>

</div>

<script src="assets/cache/jquery/jquery.min.js?v=2.1.*" type="text/javascript" charset="utf-8"></script>
<script src="assets/cache/twitter-bootstrap/js/bootstrap.min.js?v=3.*" type="text/javascript" charset="utf-8"></script>
<script src="assets/cache/ace/ace.js" type="text/javascript" charset="utf-8"></script>
<script>
    (function (){
        var editor = ace.edit("editor");
        var preview = ace.edit("preview");
        var textarea = $('textarea[name="editor"]').hide();

        // disable message 'Automatically scrolling cursor into view after selection
        //                  change this will be disabled in the next version'
        editor.$blockScrolling = Infinity;
        preview.$blockScrolling = Infinity;

        editor.getSession().setValue(localStorage.getItem('code') ||
                                     textarea.val() ||
                                     '<'+'?php'+String.fromCharCode(10));
        textarea.val(editor.getSession().getValue());
        editor.getSession().getSelection().moveCursorBy(editor.getSession().getLength(), 0);
        editor.getSession().on('change', function(){
            textarea.val(editor.getSession().getValue());
        });
        editor.setTheme("ace/theme/textmate");
        editor.getSession().setMode("ace/mode/php");

        preview.setTheme("ace/theme/terminal");
        preview.setReadOnly(true);
//        preview.getSession().setMode("ace/mode/b");


        $('#tab a').click(function (e) {
            e.preventDefault();
            $(this).tab('show');
        });

        $('#code_editor [type="submit"]')
            .on("click", function (e){
                e.preventDefault();
                // 
                var post_url = $('form').attr('action');
                var post_data = {
                        title: $('input[name="title"]').val(),
                        code: textarea.val(),
                    };
                console.log(textarea.val());
                console.log($('input[name="title"]').val());
                console.log();
                $.post(post_url, post_data, function (data, dataType){
                    // check Error or Warning
                    var annotations = [],
                        error_or_warning,
                        re = /^(.+?): (.+?) in .+? on line ([0-9]+)[\r\n]+/mg;
                    while ((error_or_warning = re.exec(data)) !== null) {
                        annotations[annotations.length] = {
                                row: parseInt(error_or_warning[3]) - 1,
                                type: error_or_warning[1].match(/error/i)   ? 'error'
                                    : error_or_warning[1].match(/warning/i) ? 'warning'
                                                                            : 'info',
                                text: error_or_warning[2]
                            };
                    }
                    editor.getSession().setAnnotations(annotations);

                    // apply preview
                    data = data.replace(re, '');
                    preview.getSession().setValue(data);
                });
            });
        
        // Auto save
        var idLazyAutoSave = undefined;
        editor.getSession().on('change', function(){
            //console.dir(editor.getSession().gtLength());
            // Auto save proccess
            if (idLazyAutoSave)
                clearTimeout(idLazyAutoSave);
            idLazyAutoSave
                = setTimeout(function (){
                        idLazyAutoSave = undefined;
                        // Auto save
                        localStorage.setItem('code', editor.getSession().getValue());
                    }, 500);
        });
    })();
</script>
</body>
</html>