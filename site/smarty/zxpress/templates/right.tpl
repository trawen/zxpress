</main>


<aside class="col-right" id="col-right">

    <div class="sidebar-search">
    <form method='GET' action='{$host}search.php'>
        {if $lng eq 'eng'}<input type="hidden" name="lng" value="eng">{/if}
        <div class="search-input-wrap">
        <input class="right" id="input_query_sidebar" name="q" type="search" placeholder="{if $lng eq 'eng'}Search...{else}Поиск...{/if}" value="{$q}" autocomplete="off">
        <div id="suggest-sidebar" class="search-suggest"></div>
        </div>
    </form>
    </div>

    <div class="sidebar-body">

    <hr>

    <div align="center">
        <div class="right-topics"><b>{if $lng eq 'eng'}Topics:{else}Темы:{/if}</b>
                {if $lng eq 'eng'}
                <a href="{$host}tag.php?id=3{$dl}">Games</a>, <a href="{$host}menu/64{$sl}">Software</a>, <a
                href="{$host}tag.php?id=56{$dl}">Press</a>, <a href="{$host}menu/1/{$sl}">Hardware</a>, <a
                href="{$host}tag.php?id=21{$dl}">Network</a>, <a href="{$host}tag.php?id=5{$dl}">Demoscene</a>, <a
                href="{$host}tag.php?id=37{$dl}">People</a>, <a href="{$host}tag.php?id=13{$dl}">Programming</a></div>
                {else}
                <a href="{$host}tag.php?id=3">Игры</a>, <a href="{$host}menu/64">Программное обеспечение</a>, <a
                href="{$host}tag.php?id=56">Пресса</a>, <a href="{$host}menu/1/">Аппаратное обеспечение</a>, <a
                href="{$host}tag.php?id=21">Сеть</a>, <a href="{$host}tag.php?id=5">Демосцена</a>, <a
                href="{$host}tag.php?id=37">Люди</a>, <a href="{$host}tag.php?id=13">Программирование</a></div>
                {/if}
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
            <div><b>{if $lng eq 'eng'}On this day...{else}В этот день...{/if} &nbsp; {$today_month}</div><br>

            <div class="right-on-this-day-links">
                {foreach from=$monday item=m name=m}
                    <a href="{$host}issue.php?id={$m.press_id_cal}{if $lng eq 'eng'}{$dl}{/if}#{$m.number_cal}"
                        class="u-nowrap">{$m.title_cal} №{$m.number_cal}{if $smarty.foreach.m.last eq false},
                        {/if}</a>

                {/foreach}
            </div>

            {if $monday[0].year_cal eq ""}{if $lng eq 'eng'}No releases :({else}Релизов не было :({/if}{/if}

            </b>
        </center>
    </div>

    <hr>

    <form method='GET' action='{$host}issue.php{if $lng eq "eng"}?lng=eng{/if}'>
        {if $lng eq 'eng'}
        <label for="sidebar-press-select" class="u-sr-only">Choose publication</label>
        {else}
        <label for="sidebar-press-select" class="u-sr-only">Выбрать издание</label>
        {/if}
        <select class="right" id="sidebar-press-select" name='id' onChange="javascript:this.parentNode.submit();">
            <option selected>{if $lng eq 'eng'}Choose publication...{else}Выбрать издание...{/if}</option>
            {section name=n loop=$press_list}
                <option class="right-select-option" value='{$press_list[n].id}'>{$press_list[n].title}</option>
            {/section}
        </select>
    </form>



    {literal}
    <script type="text/javascript">
    (function(){
        function initSuggest(inputId, dropId) {
            var input = document.getElementById(inputId);
            var drop = document.getElementById(dropId);
            if (!input || !drop) return;
            var timer = null, sel = -1;

            function hideDrop(clear) {
                drop.style.display = 'none';
                if (clear) drop.innerHTML = '';
            }

            function showDrop() {
                drop.style.display = 'block';
            }

            function dropItems() {
                return drop.querySelectorAll('div');
            }

            function setActiveItem(items, index) {
                for (var i = 0; i < items.length; i++) {
                    items[i].classList.toggle('active', i === index);
                }
            }

            input.addEventListener('input', onInput);
            input.addEventListener('keyup', onInput);

            function onInput(e) {
                if (e.keyCode === 38 || e.keyCode === 40 || e.keyCode === 13 || e.keyCode === 27) return;
                clearTimeout(timer);
                var q = input.value;
                if (q.length < 2) { hideDrop(true); return; }
                timer = setTimeout(function() {
                    fetch('/suggest.php?q=' + encodeURIComponent(q), {credentials: 'same-origin'})
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            drop.innerHTML = '';
                            sel = -1;
                            if (!data || !data.length) { hideDrop(true); return; }
                            for (var i = 0; i < data.length; i++) {
                                var row = document.createElement('div');
                                row.textContent = data[i];
                                drop.appendChild(row);
                            }
                            showDrop();
                        })
                        .catch(function() { hideDrop(true); });
                }, 300);
            }

            input.addEventListener('keydown', function(e) {
                var items = dropItems();
                if (!items.length) return;
                if (e.keyCode === 40) {
                    sel = Math.min(sel + 1, items.length - 1);
                    setActiveItem(items, sel);
                    e.preventDefault();
                } else if (e.keyCode === 38) {
                    sel = Math.max(sel - 1, 0);
                    setActiveItem(items, sel);
                    e.preventDefault();
                } else if (e.keyCode === 13 && sel >= 0) {
                    input.value = items[sel].textContent;
                    hideDrop(true);
                } else if (e.keyCode === 27) {
                    hideDrop(true);
                    sel = -1;
                }
            });

            drop.addEventListener('mousedown', function(e) {
                var row = e.target.closest('div');
                if (!row || !drop.contains(row)) return;
                input.value = row.textContent;
                hideDrop(true);
                var form = input.closest('form');
                if (form) form.submit();
            });

            input.addEventListener('blur', function() {
                setTimeout(function() { hideDrop(false); }, 200);
            });
            input.addEventListener('focus', function() {
                if (dropItems().length) showDrop();
            });
        }

        function onReady(fn) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fn);
            } else {
                fn();
            }
        }

        onReady(function() {
            initSuggest('input_query', 'suggest-main');
            initSuggest('input_query_sidebar', 'suggest-sidebar');
        });
    })();
    </script>
    {/literal}

    </div><!-- .sidebar-body -->
</aside><!-- .col-right -->
