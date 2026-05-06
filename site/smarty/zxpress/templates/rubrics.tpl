
{include file="top.tpl"}
<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>

{function name=menu level=0}     
  <ul{if $level eq 0} class="tree"{/if}>
  
  {foreach from=$data item=r}
    
    {if is_array($r.tree)}

      <li class="{if $r.last}last{/if} {if $level eq 0}first{/if}">
      	<div>
      	<a href="rubrics.php?id={$r.id}&r={$r_id}#articles" {if $id eq $r.id}style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px"{/if}>{$r.name_plain}</a> 
      	{if $r.articles}<span style="font: normal 12px Arial; color: #aaa">{$r.articles}</span>{/if}
      	</div>

      	{menu data=$r.tree level=$level+1}

      </li>



    {else}
      
      <li class="{if $r.last}last{/if} {if $level eq 0}first{/if}">
      	<a href="rubrics.php?id={$r.id}&r={$r_id}#articles" {if $id eq $r.id}style="border-bottom: 2px solid #800; margin-top: 4px; margin-bottom: 4px"{/if}>{$r.name_plain}</a>
      	{if $r.articles}<span style="font: normal 12px Arial; color: #aaa">{$r.articles}</span>{/if}
      </li>

    {/if}

  {/foreach}

  </ul>
{/function}



{menu data=$rubrics}


{if $id}
<br>
<a name="articles">
<b><h1>{$h1}</h1></b>
</a>
{/if}


{foreach from=$rubrics_articles item=ra}

{if $ra.show}
<br>
<div>
<a style="font: 13pt Georgia; color: #800;" href="{$host}issue.php?id={$ra.id_press}#{$ra.id_issue}">{$ra.press_name_plain} #{$ra.nm_issue}</a> 
<br>
</div>
{if $ra.date}<div style="padding-bottom: 8px; font: normal 12px Tahoma; color: #999">{$ra.date}</div>{/if}
{/if}

<div style="font: 13pt/15pt Times; text-align: left">
<a href="{$host}article.php?id={$ra.id_article}">{$ra.title_list nofilter}</a>
</div>

{/foreach}


{if $id}

<p onclick="scroll(0,0)">← к меню</p>

{/if}



{literal}
<script>
$(document).ready(function() {

	//$('ul.tree li:last-child').addClass('last');

});

</script>
{/literal}




{include file="right.tpl"}
{include file="footer.tpl"}

