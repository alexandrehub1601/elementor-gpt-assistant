/* ===== FORMULARIO DE CONTATO =============================== */
(function(){
  var cfg = window.CC_CONTACT || {};
  var form = document.getElementById('ccForm'); if(!form) return;
  var note = document.getElementById('ccNote');
  var en = function(){ return document.documentElement.lang === 'en'; };
  var t = function(pt, enTxt){ return en() ? enTxt : pt; };

  /* WhatsApp: mostra so se houver numero */
  var wa = (cfg.WHATSAPP || '').replace(/\D/g,'');
  if(wa){
    document.querySelectorAll('.js-wa-link').forEach(function(a){
      a.href = 'https://wa.me/' + wa; a.hidden = false;
    });
    document.querySelectorAll('.js-wa-label').forEach(function(s){
      var n = wa.replace(/^55/,'');
      s.textContent = n.length >= 10 ? '(' + n.slice(0,2) + ') ' + n.slice(2,-4) + '-' + n.slice(-4) : '+' + wa;
    });
    document.querySelectorAll('.js-wa-send').forEach(function(b){ b.hidden = false; });
  }

  function data(){
    var fd = new FormData(form);
    return {
      nome: (fd.get('nome')||'').trim(), email: (fd.get('email')||'').trim(),
      telefone: (fd.get('telefone')||'').trim(), cidade: (fd.get('cidade')||'').trim(),
      assunto: fd.get('assunto') || 'Contato', mensagem: (fd.get('mensagem')||'').trim(), fd: fd
    };
  }
  function text(d){
    return 'Nome: ' + d.nome + '\nE-mail: ' + d.email +
      (d.telefone ? '\nWhatsApp: ' + d.telefone : '') + (d.cidade ? '\nCidade: ' + d.cidade : '') +
      '\nAssunto: ' + d.assunto + '\n\n' + d.mensagem;
  }
  function say(msg, cls){ note.textContent = msg; note.className = 'fnote ' + (cls||''); }
  function valid(){
    if(form.checkValidity()) return true;
    var bad = form.querySelector(':invalid'); if(bad) bad.focus();
    say(t('Preencha nome, e-mail, mensagem e aceite a política.', 'Please fill in name, email, message and accept the policy.'), 'err');
    return false;
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if(form.querySelector('.hp').value) return;           /* robo */
    if(!valid()) return;
    var d = data();

    if(!cfg.FORM_ENDPOINT){
      location.href = 'mailto:' + cfg.EMAIL + '?subject=' + encodeURIComponent('[Site] ' + d.assunto + ' — ' + d.nome) +
        '&body=' + encodeURIComponent(text(d));
      say(t('Abrimos o seu app de e-mail com a mensagem pronta.', 'We opened your email app with the message ready.'), 'ok');
      return;
    }

    var btn = form.querySelector('[type=submit]'); btn.disabled = true;
    say(t('Enviando...', 'Sending...'));
    d.fd.append('_subject', '[Site] ' + d.assunto + ' — ' + d.nome);
    fetch(cfg.FORM_ENDPOINT, { method:'POST', body:d.fd, headers:{ 'Accept':'application/json' } })
      .then(function(r){
        return r.json().catch(function(){ return {}; }).then(function(j){ if(!r.ok) throw (j && j.message) || 0; }); })
      .then(function(){ form.reset();
        say(t('Mensagem enviada. Obrigada! Respondemos em breve.', 'Message sent. Thank you! We’ll reply soon.'), 'ok'); })
      .catch(function(msg){
        say(typeof msg === 'string' && msg ? msg :
          t('Não foi possível enviar agora. Escreva para ' + cfg.EMAIL, 'Could not send right now. Please email ' + cfg.EMAIL), 'err'); })
      .then(function(){ btn.disabled = false; });
  });

  document.querySelectorAll('.js-wa-send').forEach(function(b){
    b.addEventListener('click', function(){
      if(!valid()) return;
      window.open('https://wa.me/' + wa + '?text=' + encodeURIComponent(text(data())), '_blank', 'noopener');
    });
  });
})();
