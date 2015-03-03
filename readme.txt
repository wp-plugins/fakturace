=== Fakturace ===
Contributors: tanciciorel
Tags: fakturce, platby
Donate link: https://flattr.com/profile/tancici-orel
Requires at least: 4.1.1
Tested up to: 4.1.1
Stable tag: trunk
License: GPLv2

Fakturace, platby přes GoPay, aktivace prémiových účtů

== Description ==
Generování a zasílání faktur emailem (pro ČR), online platby přes GoPay a následná aktivace prémiových uživatelských účtů.

== Installation ==
1. nainstalovat plugin
2. uzavřít smlouvu s firmou GoPay o poskytování platebních kanálů
3. nastavit parametry v administraci WP pod záložkou **Nastavení Fakturace**
4. na zvolenou stránku přidat platební formulář pomocí shortcode `[fakturace_formular]`
5. předat pracovníkům firmy GoPay notifikační URL, které naleznete v nastavení pluginu pod záložkou **GoPay**

== Screenshots ==

1. Seznam vystavených faktur
2. Nastavení pluginu
3. Ukázka platebního formuláře

== Changelog ==

= 1.0 =

* první veřejná verze

= 1.1 =

* odstraněn vložená knihovna Titan Framework a přidána vazba na externí plugin Titan Framework
* upravena url pro notifikace z GoPay

= 1.2 =

* přidána podpora provizního systému AffilBox
* odstraněn soubor nette.phar a přidány používané knihovny přes composer