=== Fakturace ===
Contributors: tanciciorel
Tags: fakturce, platby, gopay
Donate link: https://flattr.com/profile/tancici-orel
Requires at least: 4.1.1
Tested up to: 4.1.1
Stable tag: trunk
License: GPLv2

Fakturace, platby přes GoPay, aktivace prémiových účtů

== Description ==
Fakturace a online platební brána s aktivací prémiových účtů

* generování a zasílání faktur v PDF emailem
* online platby přes GoPay
* následná aktivace prémiových uživatelských účtů

Plugin je prozatím určen pro ČR

== Installation ==
1. nainstalovat plugin
2. uzavřít smlouvu s firmou GoPay o poskytování platebních kanálů
3. nastavit parametry v administraci WP pod záložkou **Nastavení Fakturace**
4. na zvolenou stránku přidat platební formulář pomocí shortcode `[fakturace_formular]`
5. předat pracovníkům firmy GoPay notifikační URL, které naleznete v nastavení pluginu pod záložkou **GoPay**

Pro správnou funkčnost je vyžadována instalace pluginu <a href="https://wordpress.org/plugins/titan-framework/">Titan Framework</a>.
Pro vytvoření nové uživatelské role doporučuji třeba plugin <a href="https://wordpress.org/plugins/members/">Members</a>.

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

= 1.2.1 =

* změněn způsob instalace potřebných závislostí

= 1.2.2 =

* upraveno upozornění na potřebu instalace potřebných závislostí (nejde skrýt)
* přidáno doporučení pluginu <a href="https://wordpress.org/plugins/members/">Members</a>

= 1.2.3 =

* upravena aktivace účtu - pro administrátory se uživ. role nemění
