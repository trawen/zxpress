</div>


<div class="col-right" id="col-right">

    <div class="sidebar-search">
    <form method='GET' action='search.php'>
        <div class="search-input-wrap">
        <input class="right" id="input_query_sidebar" name="q" type="search" placeholder="Поиск..." value="{$q}" autocomplete="off">
        <div id="suggest-sidebar" class="search-suggest"></div>
        </div>
    </form>
    </div>

    <div class="sidebar-body">

    <hr>

    <div align="center">
        <div class="right-topics"><b>Темы:</b> <a
                href="{$host}tag.php?id=3">Игры</a>, <a href="{$host}menu/64">Программное обеспечение</a>, <a
                href="{$host}tag.php?id=56">Пресса</a>, <a href="{$host}menu/1/">Аппаратное обеспечение</a>, <a
                href="{$host}tag.php?id=21">Сеть</a>, <a href="{$host}tag.php?id=5">Демосцена</a>, <a
                href="{$host}tag.php?id=37">Люди</a>, <a href="{$host}tag.php?id=13">Программирование</a></div>
    </div>

    <hr>

    {if $lng eq 'eng'}
        <div class="right-similar-heading">Similar articles:</div>
    {else}
        <div class="right-similar-heading">Похожие статьи:</div>
    {/if}
    {foreach from=$random_articles item=r name=r}
        <div class="right-similar-item">
            {if $lng eq 'eng'}
                <a href="{$host}article.php?id={$r.id}{$dl}">{$r.title_eng nofilter}</a>
            {else}
                <a href="{$host}article.php?id={$r.id}">{$r.title nofilter}</a>
            {/if}
        </div>
    {/foreach}

    <hr>


    <div class="right-on-this-day">
        <center>
            <div><b>В этот день... &nbsp; {$today_month}</div><br>

            <div class="right-on-this-day-links">
                {foreach from=$monday item=m name=m}
                    <a href="{$host}issue.php?id={$m.press_id_cal}#{$m.number_cal}"
                        class="u-nowrap">{$m.title_cal} №{$m.number_cal}{if $smarty.foreach.m.last eq false},
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
                <option class="right-select-option" value='{$press_list[n].id}'>{$press_list[n].title}</option>
            {/section}
        </select>
    </form>



    {literal}
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
        <div class="right-counter-faded" align=center>
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

    </div><!-- .sidebar-body -->
</div><!-- .col-right -->
