---
layout: homepage
---

<header>
    <div class="inner-content">
      <a href="http://thephpleague.com/" class="league">
          Presented by The League of Extraordinary Packages
      </a>
      <h1>{{ site.data.project.title }}</h1>
      <h2>{{ site.data.project.tagline }}</h2>
      <p class="composer"><span>$ composer require league/csv</span></p>
    </div>
</header>

<main>
  <div class="example">
    <div class="inner-content">
      <h1>Usage</h1>

<div class="language-php highlighter-rouge"><pre class="highlight"><code><span class="cp">&lt;?php</span>
<span class="k">use</span> <span class="nx">League\Csv\Reader</span><span class="p">;</span>
<span class="k">use</span> <span class="nx">League\Csv\Statement</span><span class="p">;</span>

<span class="c1">//load the CSV document
</span><span class="nv">$csv</span> <span class="o">=</span> <span class="nx">Reader</span><span class="o">::</span><span class="na">createFromPath</span><span class="p">(</span><span class="s1">'/path/to/your/csv/file.csv'</span><span class="p">)</span>
    <span class="o">-&gt;</span><span class="na">setHeaderOffset</span><span class="p">(</span><span class="mi">0</span><span class="p">)</span>
    <span class="o">-&gt;</span><span class="na">addStreamFilter</span><span class="p">(</span><span class="s1">'convert.iconv.ISO-8859-1/UTF-8'</span><span class="p">)</span>
<span class="p">;</span>

<span class="c1">//build a statement
</span><span class="nv">$stmt</span> <span class="o">=</span> <span class="p">(</span><span class="k">new</span> <span class="nx">Statement</span><span class="p">())</span>
    <span class="o">-&gt;</span><span class="na">offset</span><span class="p">(</span><span class="mi">10</span><span class="p">)</span>
    <span class="o">-&gt;</span><span class="na">limit</span><span class="p">(</span><span class="mi">25</span><span class="p">)</span>
<span class="p">;</span>

<span class="c1">//query your records from the document
</span><span class="nv">$res</span> <span class="o">=</span> <span class="nv">$csv</span><span class="o">-&gt;</span><span class="na">select</span><span class="p">(</span><span class="nv">$stmt</span><span class="p">)</span><span class="o">-&gt;</span><span class="na">fetchAll</span><span class="p">();</span>
</code></pre>
</div>
    </div>
  </div>


  <div class="highlights">
    <div class="inner-content">
      <div class="column one">
        <h1>Highlights</h1>
        <div class="description">
        <p>The library was designed for developers who want to deal with CSV data using modern code and without the high levels of bootstrap and low-levels of usefulness provided by existing core functions or third party-code.</p>
        </div>
      </div>
      <div class="column two">
        <ol>
          <li><p>Simple API</p></li>
          <li><p>Read and Write to CSV documents in a memory efficient and scalable way</p></li>
          <li><p>Support PHP Stream filtering capabilities</p></li>
          <li><p>Transform CSV documents into popular formats (JSON, XML or HTML)</p></li>
          <li><p>Framework-agnostic</p></li>
        </ol>
      </div>
    </div>
  </div>

  <div class="documentation">
    <div class="inner-content">
      <h1>Releases</h1>

      <div class="version next">
        <h2>Next/master</h2>
        <div class="content">
          <p><code>League\Csv 9.0</code></p>
          <ul>
            <li>Requires: <strong>PHP >= 7.0.0</strong></li>
            <li>Release Date: <strong>TBD</strong></li>
            <li>Supported Until: <strong>TBD</strong></li>
          </ul>
          <p><a href="/9.0/">Full Documentation</a></p>
        </div>
      </div>

      <div class="version current">
        <h2>Current Stable Release</h2>
        <div class="content">
          <p><code>League\Csv 8.0</code></p>
          <ul>
            <li>Requires: <strong>PHP >= 5.5.0</strong></li>
            <li>Release Date: <strong>2015-12-11</strong></li>
            <li>Supported Until: <strong>TBD</strong></li>
          </ul>
          <p><a href="/8.0/">Full Documentation</a></p>
        </div>
      </div>

      <div class="version legacy">
        <h2>No longer Supported</h2>
        <div class="content">
          <p><code>League\Csv 7.0</code></p>
          <ul>
            <li>Requires: <strong>PHP >= 5.4.0</strong></li>
            <li>Release Date: <strong>2015-02-19</strong></li>
            <li>Supported Until: <strong>2016-06-11</strong></li>
          </ul>
          <p><a href="/7.0/">Full Documentation</a></p>
        </div>
      </div>

      <p class="footnote">Once a new major version is released, the previous stable release remains supported for six (6) more months through patches and/or security fixes.</p>

    </div>
  </div>

  <div class="questions">
    <div class="inner-content">
      <h1>Questions?</h1>
      <p><strong>League\Csv</strong> was created by Ignace Nyamagana Butera. Find him on Twitter at <a href="https://twitter.com/nyamsprod">@nyamsprod</a>.</p>
    </div>
  </div>
</main>