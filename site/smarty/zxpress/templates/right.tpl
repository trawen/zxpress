</div>


<div class="col-right" id="col-right">

    <form method='GET' action='search.php'>
        <div style="position:relative">
        <input class="right" id="input_query_sidebar" name="q" type="search" placeholder="Поиск..." value="{$q}" autocomplete="off">
        <div id="suggest-sidebar" class="search-suggest"></div>
        </div>
    </form>

    <hr>

    <div align="center">
        <div style="font: normal 13pt/17pt Times; word-wrap: break-word"><b>Темы:</b> <a
                href="{$host}tag.php?id=3">Игры</a>, <a href="{$host}menu/64">Программное обеспечение</a>, <a
                href="{$host}tag.php?id=56">Пресса</a>, <a href="{$host}menu/1/">Аппаратное обеспечение</a>, <a
                href="{$host}tag.php?id=21">Сеть</a>, <a href="{$host}tag.php?id=5">Демосцена</a>, <a
                href="{$host}tag.php?id=37">Люди</a>, <a href="{$host}tag.php?id=13">Программирование</a></div>
    </div>

    <hr>

    {if $lng eq 'eng'}
        <div style="font: bold 13pt/17pt Times;">Similar articles:</div>
    {else}
        <div style="font: bold 13pt/17pt Times;">Похожие статьи:</div>
    {/if}
    {foreach from=$random_articles item=r name=r}
        <div style="font: normal 13pt/17pt Times;  padding-top: 8px; word-wrap: break-word">
            {if $lng eq 'eng'}
                <a href="{$host}article.php?id={$r.id}{$dl}">{$r.title_eng nofilter}</a>
            {else}
                <a href="{$host}article.php?id={$r.id}">{$r.title nofilter}</a>
            {/if}
        </div>
    {/foreach}

    <hr>


    <div style="font: normal 13pt Times;">
        <center>
            <div><b>В этот день... &nbsp; {$today_month}</div><br>

            <div style="font: bold 13pt/17pt Times">
                {foreach from=$monday item=m name=m}
                    <a href="{$host}issue.php?id={$m.press_id_cal}#{$m.number_cal}"
                        style="white-space: nowrap">{$m.title_cal} №{$m.number_cal}{if $smarty.foreach.m.last eq false},
                        {/if}</a>

                {/foreach}
            </div>

            {if $monday[0].year_cal eq ""}Релизов не было :({/if}

            </b>
        </center>
    </div>

    <hr>

    <form method='GET' action='{$host}issue.php'>
        <select class="right" name='id' onChange="javascript:this.parentNode.submit();">
            <option selected>Выбрать издание...</option>
            {section name=n loop=$press_list}
                <option style=" border: none" value='{$press_list[n].id}'>{$press_list[n].title}</option>
            {/section}
        </select>
    </form>



    {literal}
    <style>
    .search-suggest {
        display:none; position:absolute; left:0; right:0; top:100%;
        background:#fff; border:1px solid #ccc; border-top:none;
        z-index:9999; max-height:200px; overflow-y:auto;
        font:normal 13pt/17pt Times;
    }
    .search-suggest div {
        padding:4px 8px; cursor:pointer;
    }
    .search-suggest div.active, .search-suggest div:hover {
        background:#e8e8e8;
    }
    </style>
    <script type="text/javascript">
    (function(){
        function initSuggest(inputId, dropId) {
            var $input = $('#' + inputId), $drop = $('#' + dropId);
            if (!$input.length) return;
            var timer = null, sel = -1;

            $input.bind('input keyup', function(e) {
                if (e.keyCode === 38 || e.keyCode === 40 || e.keyCode === 13 || e.keyCode === 27) return;
                clearTimeout(timer);
                var q = $input.val();
                if (q.length < 2) { $drop.hide().empty(); return; }
                timer = setTimeout(function() {
                    $.getJSON('/suggest.php', {q: q}, function(data) {
                        $drop.empty(); sel = -1;
                        if (!data || !data.length) { $drop.hide(); return; }
                        for (var i = 0; i < data.length; i++) {
                            $('<div>').text(data[i]).appendTo($drop);
                        }
                        $drop.show();
                    });
                }, 300);
            });

            $input.keydown(function(e) {
                var items = $drop.children();
                if (!items.length) return;
                if (e.keyCode === 40) { sel = Math.min(sel + 1, items.length - 1); items.removeClass('active').eq(sel).addClass('active'); e.preventDefault(); }
                else if (e.keyCode === 38) { sel = Math.max(sel - 1, 0); items.removeClass('active').eq(sel).addClass('active'); e.preventDefault(); }
                else if (e.keyCode === 13 && sel >= 0) { $input.val(items.eq(sel).text()); $drop.hide(); }
                else if (e.keyCode === 27) { $drop.hide(); sel = -1; }
            });

            $drop.delegate('div', 'mousedown', function() {
                $input.val($(this).text());
                $drop.hide();
                $input.closest('form').submit();
            });

            $input.blur(function() { setTimeout(function(){ $drop.hide(); }, 200); });
            $input.focus(function() { if ($drop.children().length) $drop.show(); });
        }

        $(function() {
            initSuggest('input_query', 'suggest-main');
            initSuggest('input_query_sidebar', 'suggest-sidebar');
        });
    })();
    </script>
    {/literal}

    {literal}
        <div style="filter:progid:DXImageTransform.Microsoft.Alpha(opacity=20); opacity: 0.1;" align=center>
            <!--LiveInternet counter-->
            <script type="text/javascript">
                <!--
            document.write("<a href='https://www.liveinternet.ru/stat/zxpress.ru/queries.html' " +
                "target=_blank><img src='https://counter.yadro.ru/hit?t17.1;r" +
                escape(document.referrer) + ((typeof(screen) == "undefined") ? "" :
                    ";s" + screen.width + "*" + screen.height + "*" + (screen.colorDepth ?
                        screen.colorDepth : screen.pixelDepth)) + ";u" + escape(document.URL) +
                ";" + Math.random() +
                "' alt='' title='LiveInternet: показано число просмотров за 24" +
                " часа, посетителей за 24 часа и за сегодня' " +
                "border=0 width=1 height=1><\/a>") //
            -->
            </script>
            <!--/LiveInternet-->
        </div>
    {/literal}
</div>
