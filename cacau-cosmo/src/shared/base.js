<script>
(function(){
  /* ===== MENU LATERAL ====================================== */
  var drawer=document.getElementById('drawer'), scrim=document.getElementById('scrim'),
      burger=document.getElementById('burger'), closeBtn=document.getElementById('close');
  if(drawer&&burger){
    var open=function(){
      scrim.hidden=false;
      requestAnimationFrame(function(){drawer.classList.add('open');scrim.classList.add('open')});
      drawer.setAttribute('aria-hidden','false');
      burger.setAttribute('aria-expanded','true');
      document.body.style.overflow='hidden';
      drawer.querySelector('.nav a').focus({preventScroll:true});
    };
    var close=function(){
      drawer.classList.remove('open'); scrim.classList.remove('open');
      drawer.setAttribute('aria-hidden','true');
      burger.setAttribute('aria-expanded','false');
      document.body.style.overflow='';
      setTimeout(function(){scrim.hidden=true},700);
      burger.focus({preventScroll:true});
    };
    burger.addEventListener('click',open);
    closeBtn.addEventListener('click',close);
    scrim.addEventListener('click',close);
    document.addEventListener('keydown',function(e){
      if(e.key==='Escape'&&drawer.classList.contains('open'))close();
    });
    drawer.querySelectorAll('a').forEach(function(a){a.addEventListener('click',close)});
  }

  /* ===== LINK DA PAGINA ATUAL ============================== */
  var here=location.pathname.replace(/\/+$/,'')||'/';
  document.querySelectorAll('.nav a, .footer__nav a').forEach(function(a){
    var p=(a.getAttribute('href')||'').replace(/\/+$/,'')||'/';
    if(p===here||(p!=='/'&&here.indexOf(p+'/')===0)) a.setAttribute('aria-current','page');
  });

  /* ===== TOPO GANHA FUNDO ================================== */
  var bar=document.getElementById('topbar');
  if(bar){
    var f=function(){bar.classList.toggle('solid',window.scrollY>60)};
    window.addEventListener('scroll',f,{passive:true}); f();
  }

  /* ===== REVEAL ============================================ */
  var els=document.querySelectorAll('.rv');
  if(!('IntersectionObserver' in window)){els.forEach(function(e){e.classList.add('in')})}
  else{
    var io=new IntersectionObserver(function(en){
      en.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}});
    },{threshold:.14,rootMargin:'0px 0px -6% 0px'});
    els.forEach(function(e){io.observe(e)});
  }

  /* ===== CAMPO ESTELAR ===================================== */
  function paint(c){
    var ctx=c.getContext('2d'), dpr=Math.min(window.devicePixelRatio||1,2),
        r=c.getBoundingClientRect(), density=+c.getAttribute('data-density')||6000;
    if(!r.width||!r.height)return;
    c.width=r.width*dpr; c.height=r.height*dpr; ctx.setTransform(dpr,0,0,dpr,0,0);
    ctx.clearRect(0,0,r.width,r.height);
    var n=Math.round(r.width*r.height/density);
    for(var i=0;i<n;i++){
      var x=Math.random()*r.width, y=Math.random()*r.height, m=Math.random(),
          rad=.3+Math.pow(m,3)*1.6;
      ctx.beginPath();
      ctx.fillStyle = m>.95 ? 'rgba(201,168,76,.9)' : 'rgba(241,232,208,'+(.16+m*.42)+')';
      ctx.arc(x,y,rad,0,Math.PI*2); ctx.fill();
    }
  }
  function all(){document.querySelectorAll('canvas.stars').forEach(paint)}
  if(document.readyState==='complete')all(); else window.addEventListener('load',all);
  var t; window.addEventListener('resize',function(){clearTimeout(t);t=setTimeout(all,220)});
  if(burger)burger.addEventListener('click',function(){setTimeout(all,140)});

  /* ===== CONTADOR DO CARRINHO (WooCommerce Store API) ====== */
  var cartN=document.getElementById('cartN');
  function cart(){
    if(!cartN||!window.fetch)return;
    fetch('/wp-json/wc/store/v1/cart',{credentials:'same-origin'})
      .then(function(r){return r.ok?r.json():null})
      .then(function(d){
        if(!d)return;
        var n=d.items_count||0;
        cartN.textContent=n; cartN.classList.toggle('on',n>0);
      }).catch(function(){});
  }
  cart();
  if(window.jQuery){jQuery(document.body).on('added_to_cart removed_from_cart updated_cart_totals',cart)}

  document.querySelectorAll('.js-ano').forEach(function(e){e.textContent=new Date().getFullYear()});
})();

/* ===== IDIOMA — PT / EN ===================================== */
(function(){
  var STORAGE_KEY = 'cc-lang';
  var dict = {
    't-nav-home':    'Home',
    't-nav-shop':    'Shop',
    't-nav-about':   'About us',
    't-nav-journal': 'Journal',
    't-contact':     'Contact',
    't-btncircle':   'Join the Circle',
    't-drawerquote': '&ldquo;A small universe between<br>cacao and cosmos.&rdquo;',
    't-footerplace': 'Rio de Janeiro, Brazil',
    't-footerclaim': 'Time is an ingredient.',
    't-privacy':     'Privacy Policy',
    't-terms':       'Terms'
  };
  var extra = window.CC_DICT || {};
  for (var k in extra) dict[k] = extra[k];

  var innerEls = Array.prototype.slice.call(document.querySelectorAll('[data-i18n]'));
  var phEls = Array.prototype.slice.call(document.querySelectorAll('[data-i18n-placeholder]'));
  innerEls.forEach(function(el){ el.dataset.pt = el.innerHTML; });
  phEls.forEach(function(el){ el.dataset.ptPlaceholder = el.placeholder; });

  function apply(lang){
    var isEN = lang === 'en';
    innerEls.forEach(function(el){
      var key = el.getAttribute('data-i18n');
      el.innerHTML = isEN ? (dict[key] || el.dataset.pt) : el.dataset.pt;
    });
    phEls.forEach(function(el){
      var key = el.getAttribute('data-i18n-placeholder');
      el.placeholder = isEN ? (dict[key] || el.dataset.ptPlaceholder) : el.dataset.ptPlaceholder;
    });
    document.documentElement.lang = isEN ? 'en' : 'pt-BR';
    document.querySelectorAll('.lang button, .drawer__lang button').forEach(function(b){
      var on = b.getAttribute('data-lang') === lang;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    try{ localStorage.setItem(STORAGE_KEY, lang); }catch(e){}
  }
  document.querySelectorAll('.lang button, .drawer__lang button').forEach(function(b){
    b.addEventListener('click', function(){ apply(b.getAttribute('data-lang')); });
  });
  var saved = null;
  try{ saved = localStorage.getItem(STORAGE_KEY); }catch(e){}
  if(saved === 'en') apply('en');
})();
</script>
