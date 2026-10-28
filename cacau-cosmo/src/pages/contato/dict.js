/* ===== CONFIGURACAO DO CONTATO ==============================
   WHATSAPP ...... só números, com DDI+DDD. Ex.: '5521999999999'.
                   Vazio = esconde o WhatsApp da página.
   FORM_ENDPOINT . para onde o formulário envia (POST, resposta JSON).
                   Padrão: o painel do WordPress (menu "Mensagens"),
                   criado pelo código wordpress/mensagens-contato.php.
                   Vazio = abre o app de e-mail do visitante já preenchido.
   ============================================================ */
window.CC_CONTACT = {
  EMAIL: 'hello@cacaucosmo.com',
  WHATSAPP: '',
  FORM_ENDPOINT: '/wp-json/cacau/v1/contato'
};

window.CC_DICT = {
  't-ct-kicker':      'Contact',
  't-ct-h1':          'Let’s <span class="em">talk.</span>',
  't-ct-sub':         'Orders, gifts, partnerships, press or just a good conversation about cacao. We reply calmly &mdash; and always.',
  't-ct-ch-label':    'Channels',
  't-ct-ch-h2':       'Where to find us.',
  't-ct-ch-intro':    'Choose whichever path feels most comfortable.',
  't-ct-place-label': 'Studio',
  't-ct-note':        '&ldquo;We reply within 2 business days.&rdquo;',
  't-ct-form-label':  'Message',
  't-ct-form-h2':     'Write to us.',
  't-ct-f-nome':      'Name',
  't-ct-ph-nome':     'Your name',
  't-ct-opt':         '(optional)',
  't-ct-f-cidade':    'City',
  't-ct-f-assunto':   'Subject',
  't-ct-a1':          'Order',
  't-ct-a2':          'Corporate gifts',
  't-ct-a3':          'Wholesale &amp; partnerships',
  't-ct-a4':          'Press',
  't-ct-a5':          'Other',
  't-ct-f-msg':       'Message',
  't-ct-ph-msg':      'Tell us a little...',
  't-ct-consent':     'I agree to the <a href="/politica-de-privacidade/">Privacy Policy</a> and allow Cacau Cosmo to contact me.',
  't-ct-send':        'Send message',
  't-ct-send-wa':     'Send via WhatsApp',
  't-ct-faq-label':   'Questions',
  't-ct-faq-h2':      'Frequently asked.',
  't-ct-faq-intro':   'Didn’t find what you were looking for? Write to us using the form above.',
  't-ct-q1':          'Do you ship across Brazil?',
  't-ct-r1':          'Yes. Shipping cost and time are calculated in the cart from your postcode. On very hot days we may hold shipments for a few days to protect the chocolate.',
  't-ct-q2':          'How should I store the chocolate?',
  't-ct-r2':          'Somewhere cool, dry and away from light, ideally between 16&nbsp;°C and 20&nbsp;°C. Avoid the fridge: humidity and odours change texture and flavour.',
  't-ct-q3':          'Do you make corporate gifts and custom boxes?',
  't-ct-r3':          'We do. Select &ldquo;Corporate gifts&rdquo; in the form and tell us quantity, date and occasion. As we work in small batches, the sooner the better.',
  't-ct-q4':          'I’d like to stock Cacau Cosmo. How does it work?',
  't-ct-r4':          'We love partners who share the same care. Choose &ldquo;Wholesale &amp; partnerships&rdquo; in the form and tell us a bit about your space.',
  't-ct-q5':          'What does bean-to-bar mean?',
  't-ct-r5':          'It means we make chocolate from the cacao bean &mdash; roasting, grinding, refining and tempering happen here, in small batches. <a href="/quem-somos/#processo" style="text-decoration:underline">See the process</a>.',
  't-ct-ig-h2':       'Follow the next batch.',
  't-ct-ig-cta':      'Follow on Instagram'
};
