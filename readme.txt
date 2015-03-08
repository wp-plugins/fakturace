=== Fakturace ===
Contributors: tanciciorel
Tags: fakturce, platby, gopay
Donate link: https://flattr.com/profile/tancici-orel
Requires at least: 3.9.2
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


Videonávod jak jednoduše prodávat ebook nebo členskou sekci na WordPressu.

https://www.youtube.com/watch?v=RY7xwEyQh-s&vq=auto



== Installation ==
1. nainstalovat plugin
2. uzavřít smlouvu s firmou GoPay o poskytování platebních kanálů
3. nastavit parametry v administraci WP pod záložkou **Nastavení Fakturace**
4. na zvolenou stránku přidat platební formulář pomocí shortcode `[fakturace_formular]`
5. předat pracovníkům firmy GoPay notifikační URL, které naleznete v nastavení pluginu pod záložkou **GoPay**

Pro správnou funkčnost je vyžadována instalace pluginu <a href="https://wordpress.org/plugins/titan-framework/">Titan Framework</a>.

Pro vytvoření nové uživatelské role doporučuji třeba plugin <a href="https://wordpress.org/plugins/members/">Members</a>.

**Při odinstalování pluginu Fakturace budou smazány všechny faktury a nastavení.**

Zálohujte svá data. Plugin je poskytován tak, jak je. Jeho funkčnost je ověřena, každopádně zodpovědnost za svá data máte Vy sami.
Pro zálohování doporučuji například zdarma dostupný plugin <a href="https://wordpress.org/plugins/backwpup/">BackWPup Free</a>, který sám rád používám.

Na záložce <a href="/plugins/fakturace/">Description</a> je videonávod s instalací.



== Screenshots ==

1. Seznam vystavených faktur
2. Nastavení pluginu
3. Ukázka platebního formuláře



== Frequently Asked Questions ==

= Chyba po instalaci =

Po aktivaci souvisejícího pluginu **Titan framework** se zobrazuje chyba:

`Fatal error: Cannot redeclare class scss_formatter_nested in ...`

K tomu může dojít, pokud některý z dalších pluginů na webu používá **Titan framework** jako vloženou knihovnu (součást kódu).
Tím dochází k duplikaci kódu, což je chyba.

Zkuste na serveru vyhledat, který plugin obsahuje soubor `class-titan-framework.php`.
Samozřejmě mimo adresáře `titan-framework`, což je přímo adresář **Titan frameworku**.

Pokud se Vám zobrazují zprávy **Warning:** a v cestě souboru je **/nette/**, aktualizujte plugin Fakturace alespoň na verzi 1.2.9.



== Upgrade Notice ==

= 1.2.9 =
Opraveny závislosti Nette frameworku, takže zmizela upozornění při zapnutém ladícím režimu WordPressu.



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

= 1.2.4 =

* vylepšeno generování faktury (jméno a příjmení zákazníka, pokud není jméno firmy)
* informace o dříve vytvořeném přístupu v emailu, pokud zákazník již má účet
* informace o rozšířené verzi pluginu na stránce s informacemi

= 1.2.5 =

* opravena komunikace s GoPay v provozním režimu

= 1.2.6 =

* upraveny informace v nastavení pluginu

= 1.2.7 =

* při odinstalování plugin smaže všechna svá nastavení a faktury (zálohujte data)

= 1.2.8 =

* úpravy doporučených pluginů

= 1.2.9 =

* doladění knihovny Nette frameworku
