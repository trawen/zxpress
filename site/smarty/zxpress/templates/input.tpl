{include file="top.tpl"}
<TABLE cellSpacing=0 cellPadding=0 align="center">
<TBODY>
<TR>
<TD>

<center><H1>Вход в кладовые ;)</H1></center><br><br>

<br><br>
{if $login eq 1}
{if $logout_notice|default:0}<p style="color: green"><strong>Вы вышли из системы.</strong></p>{/if}
{if $session_timeout_notice|default:0}<p style="color: #a41e00"><strong>Сессия истекла из‑за неактивности. Войдите снова.</strong></p>{/if}
<div style="position: absolute; left: 870px; top: 4px">
<div style="font: bold 11px Verdana; ">Вы <span style="color: red">{$username}</span></div>
</div>
<p style="margin-top: 1em">
<form method="post" action="logout.php" style="display:inline">
<input type="hidden" name="csrf_token" value="{$csrf_token}">
<button type="submit" style="background-color: black; color: white; border: 1px solid #c5c1ac; font: bold 12px Times">Выйти</button>
</form>
</p>
{else}
<center>
{if $login_error_no_rights|default:0}
<p style="color: #a41e00; margin-bottom: 1em"><strong>Учётная запись найдена, но нет прав администратора (в БД users.level должно быть 1 или NULL).</strong></p>
{elseif $login_error|default:0}
<p style="color: #a41e00; margin-bottom: 1em"><strong>Неверный логин или пароль.</strong></p>
{/if}
<form method='POST'>
<input type="hidden" name="csrf_token" value="{$csrf_token}">

<input type="text" name="username" size="8" style="border: 1px solid #c5c1ac; background-color: #F2EFDE; width: 60px" maxlength="32"> 
<input type="password" name="password" size="8" style="border: 1px solid #c5c1ac; background-color: #F2EFDE; width: 60px" maxlength="256" autocomplete="current-password">
<button type="submit" name="auth_submit" value="1" style="background-color: black; color: white; border: 1px solid #c5c1ac; font: bold 12px Times">войти</button>



</form>
</center>
{/if}


<BR>
</TD></TR></TBODY></TABLE>
{include file="right.tpl"}
{include file="footer.tpl"}