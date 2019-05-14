<?php declare(strict_types=1);

/*
 * dockr-cli phpinfo() mod
 *
 * Date: 11.05.19 16:02
 */

/**
 * phpinfo() Xdebug extra information to verify remote settings
 */
$debugInfo = call_user_func(static function () {
    $result = [
        'loaded' => 'No',
        'remote_enabled' => 'No',
        'debug_active' => 'No',
    ];
    if (!extension_loaded('xdebug')) {
        return $result;
    }
    $result['loaded'] = 'Yes';
    ini_get('xdebug.remote_enable') && $result['remote_enabled'] = 'Yes';
    $active = xdebug_is_debugger_active();
    $active && $result['debug_active'] = 'Yes';

    $cookie = $_COOKIE['XDEBUG_SESSION'] ?? null;
    $config = $_SERVER['XDEBUG_CONFIG'] ?? null;

    # xdebug_break();

    return $result;
});


$logo = 'data:image/svg+xml;base64,' . base64_encode(
        strtr(
            file_get_contents(__DIR__ . '/img/dockr.svg'),
            ['172mm' => '64px']
        )
    );

ob_start();
    phpinfo();
$buffer = ob_get_clean();

$doc = new DOMDocument();
$doc->loadHTML($buffer);
$xpath = new DOMXPath($doc);

$fragment = $doc->createDocumentFragment();
$fragment->appendXML(<<<TAG
<table><tr class="v"><td>
<a href="https://github.com/dugajean/dockr-cli"><img src="$logo" alt="dockr-cli Logo" style="margin:.2em"/></a>
<h1 class="p">dockr-cli</h1>
Xdebug extension loaded: $debugInfo[loaded]<br />
Remote debugging enabled: $debugInfo[remote_enabled]<br />
Debugging session started: $debugInfo[debug_active]<br />
</td></tr></table>

<!-- phpinfo() search -->
<form style="position: sticky; top: 0;">
<input/>
</form>
<script defer="defer">//<![CDATA[
/*
 * phpinfo() search javascript (~ES5/6)
 */
const w = window;
const d = document;

const q = d.querySelector.bind(d);
const query = function* (selector, element) {
    yield* (element || d).querySelectorAll(selector);
};

const walk = function* (next, start) {
    for (let look = next(start); look !== null; look = next(look)) 
        yield look;                
};
    
const filter = function* (condition, iter) {
    for (const item of iter)
        if (condition(item)) yield item;    
};

const aslong = function* (condition, iter) {
    for (const item of iter) {
        if (!condition(item)) break;            
        yield item;
    }
};

const on = function(probe, condition, result) {
    if (probe !== null && condition(probe) && result)
        return result(probe); 

    return null;
};

const first = function(condition, iter) {
    for (item of iter) 
        if (condition === null || condition(item)) 
            return item;

    return null;
};

const has = function (condition, iter) {
    for (item of filter(condition, iter))
        return true;

    return false;
};

const name = e => e.tagName;
const height = e => e.clientHeight;
const visible = e => !e.hidden;
const parent = e => e.parentElement;
const forward = e => e.nextElementSibling;
const backward = e => e.previousElementSibling;

const string = function (e, otherwise) {
    return ("string" === typeof e) ? e 
         : otherwise(e);
};

const isNot = function(func) {
    return e => !func(e);        
};

const isString = function(str, map) {
    str = string(str, map);
    return e => map(e) === str;
};

const is = e => !!e;

const isName = e => isString(e, name);
const notName = e => isNot(isName(e));

const reduce = function (aggregate, func, iter) {
    for (const item of iter)
        aggregate = func(aggregate, item);

    return aggregate;
};

const map = function* (func, iter) {
    for (const item of iter)
        yield func(item);      
};

const sum = function(iter) {
    return reduce(0, (a, e) => a + e, iter);
};

const look = function (next, start) {
    return first(notName(start), walk(next, start));
};

const event = function(e, type, listener, extra) {
    e.addEventListener(type, listener, extra);
};

const sections = new Set();
sections.empty = true;
const search_terms = function (terms) {
    let regExp = new RegExp(terms.replace(/[.*+?^\${}()|[\\]\\\\]/g, "\\\\$&"), "i");
    let tables = new WeakSet();
    tables.has = tables.has.bind(tables); 
    for (const e of query("table tr td.e, table tr:not(.h) td:not([class])")) {
        let row = parent(e);
        row.hidden = ! regExp.test(row.innerText);
        tables.add(parent(parent(row)));
    };        
    
    for (const table of query("table")) {
        table.hidden = false;            
        table.hidden = terms.length && (
            height(table) < 2
            || (
                table.rows[0].className === "h" 
                && height(table.tBodies[0]) === sum(map(height, query('tr.h', table)))                    
            )
        );            
        sections.empty && on(look(backward, table), isName('H2'), h2 => sections.add(h2))            
    }
    
    sections.empty = false;  
            
    sections.forEach(h2 => h2.hidden = !has(visible, aslong(isName('TABLE'), walk(forward, h2))));

    history.replaceState({}, "search for " + terms, "#" + encodeURIComponent(terms));
};

const input = q("form input");

let c = 0;
const search = function(terms) {
    c++;
    w.setTimeout(function() {
        if (c === 0) {return;}        
        c = 0;
        const untie = ("function" === typeof terms) ? terms() : terms;
        input.value = untie;
        search_terms(untie);
    }, 100);
};

event(input, "input", function() {
    search(function() {return this.value;}.bind(this));
}, false);

const hash = function(string) {
    string = w.location.hash;
    search(decodeURIComponent(string).replace(/^#/, ''));
};

event(w, "hashchange", () => hash(null), false);
event(d, "DOMContentLoaded", () => hash(null), false);

//]]></script>
TAG
);

$xpath->evaluate('//title/text()')[0]->insertData(0, 'dockr-cli - ');

$table = $xpath->evaluate('//table[2]')[0];
$table->parentNode->insertBefore($fragment, $table);

$doc->saveHTMLFile('php://output');
