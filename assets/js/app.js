/* WorkConnects — JavaScript pur, aucune librairie externe */

/* ---------- Notifications ---------- */
function toggleNotif(ev){
  ev.stopPropagation();
  var p = document.getElementById('notifPanel');
  if (p) p.classList.toggle('open');
}
document.addEventListener('click', function(){
  var p = document.getElementById('notifPanel');
  if (p) p.classList.remove('open');
});

/* ---------- Onglets ---------- */
function initTabs(){
  document.querySelectorAll('[data-tabs]').forEach(function(group){
    var tabs = group.querySelectorAll('.tab');
    tabs.forEach(function(tab){
      tab.addEventListener('click', function(){
        var target = tab.dataset.tab;
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.querySelectorAll('[data-panel]').forEach(function(p){
          p.classList.toggle('active', p.dataset.panel === target);
        });
      });
    });
  });
}

/* ---------- Sélection de rôle ---------- */
function initRoles(){
  document.querySelectorAll('.role-opt').forEach(function(opt){
    opt.addEventListener('click', function(){
      var name = opt.dataset.target;
      document.querySelectorAll('.role-opt[data-target="'+name+'"]').forEach(o => o.classList.remove('selected'));
      opt.classList.add('selected');
      var input = document.getElementById(name);
      if (input) { input.value = opt.dataset.value; }
      // Affiche les champs conditionnels
      document.querySelectorAll('[data-role-block]').forEach(function(b){
        b.style.display = (b.dataset.roleBlock === opt.dataset.value) ? '' : 'none';
        b.querySelectorAll('input,select,textarea').forEach(function(f){
          if (f.dataset.req === '1') f.required = (b.dataset.roleBlock === opt.dataset.value);
        });
      });
    });
  });
}

/* ---------- Tags de compétences ---------- */
function initTags(){
  document.querySelectorAll('.tagbox').forEach(function(box){
    var hidden = document.getElementById(box.dataset.input);
    if (!hidden) return;
    var current = hidden.value ? hidden.value.split(',').map(s=>s.trim()).filter(Boolean) : [];
    box.querySelectorAll('.tag-opt').forEach(function(tag){
      if (current.indexOf(tag.textContent.trim()) > -1) tag.classList.add('on');
      tag.addEventListener('click', function(){
        tag.classList.toggle('on');
        var sel = [];
        box.querySelectorAll('.tag-opt.on').forEach(t => sel.push(t.textContent.trim()));
        hidden.value = sel.join(',');
      });
    });
  });
}

/* ---------- Assistant multi-étapes ---------- */
var wzIndex = 0;
function initWizard(){
  var wiz = document.getElementById('wizard');
  if (!wiz) return;
  var panels = wiz.querySelectorAll('.wz-panel');
  var steps  = wiz.querySelectorAll('.wz-step');
  var btnPrev = document.getElementById('wzPrev');
  var btnNext = document.getElementById('wzNext');
  var btnSubmit = document.getElementById('wzSubmit');

  function render(){
    panels.forEach((p,i) => p.classList.toggle('active', i === wzIndex));
    steps.forEach(function(s,i){
      s.classList.toggle('active', i === wzIndex);
      s.classList.toggle('done', i < wzIndex);
    });
    btnPrev.style.visibility = wzIndex === 0 ? 'hidden' : 'visible';
    var last = wzIndex === panels.length - 1;
    btnNext.style.display   = last ? 'none' : '';
    btnSubmit.style.display = last ? '' : 'none';
    if (last) buildRecap();
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function validate(){
    var ok = true;
    panels[wzIndex].querySelectorAll('[required]').forEach(function(f){
      if (!f.value.trim()){
        f.style.borderColor = 'var(--red)';
        ok = false;
      } else {
        f.style.borderColor = '';
      }
    });
    if (!ok) alert('Merci de compléter les champs obligatoires de cette étape.');
    return ok;
  }

  function buildRecap(){
    var box = document.getElementById('recapBox');
    if (!box) return;
    var v = id => (document.getElementById(id) || {}).value || '—';
    var cat = document.getElementById('categorie');
    box.innerHTML = [
      ['Titre du projet', v('titre')],
      ['Catégorie', cat ? cat.options[cat.selectedIndex].text : '—'],
      ['Compétences requises', v('competences')],
      ['Budget estimé', euroFmt(v('budget_min'), 5000) + ' – ' + euroFmt(v('budget_max'), 5000)],
      ['Délai souhaité', v('delai')],
      ['Date limite', v('date_limite')]
    ].map(r => '<div class="recap-row"><span>'+r[0]+'</span><strong>'+escapeHtml(r[1])+'</strong></div>').join('');
  }

  btnNext.addEventListener('click', function(){ if (validate() && wzIndex < panels.length-1){ wzIndex++; render(); } });
  btnPrev.addEventListener('click', function(){ if (wzIndex > 0){ wzIndex--; render(); } });
  render();
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

/* ---------- Compteur de caractères ---------- */
function initCounters(){
  document.querySelectorAll('[data-counter]').forEach(function(ta){
    var out = document.getElementById(ta.dataset.counter);
    if (!out) return;
    var upd = () => out.textContent = ta.value.length + ' caractères';
    ta.addEventListener('input', upd); upd();
  });
}

/* ---------- Connexion démo en un clic ---------- */
function fillLogin(email, pass){
  var e = document.getElementById('email'), p = document.getElementById('password');
  if (e && p){ e.value = email; p.value = pass; e.form.submit(); }
}

/* ---------- Scroll to bottom messagerie ---------- */
function initChat(){
  var b = document.getElementById('chatBody');
  if (b) b.scrollTop = b.scrollHeight;
}

/* ---------- Filtre de tableau ---------- */
function filterTable(input, tableId){
  var q = input.value.toLowerCase();
  document.querySelectorAll('#'+tableId+' tbody tr').forEach(function(tr){
    tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', function(){
  initTabs(); initRoles(); initTags(); initWizard(); initCounters(); initChat(); initBudgetRange();
});

/* ---------- Double curseur de budget (1 € – 5 000 €) ---------- */
function euroFmt(n, max){
  var v = Number(n);
  var plus = (max !== undefined && v >= Number(max)) ? '+' : '';
  return v.toLocaleString('fr-FR') + ' \u20AC' + plus;
}

function initBudgetRange(){
  var wrap = document.getElementById('budgetRange');
  if (!wrap) return;

  var min  = document.getElementById('budget_min');
  var max  = document.getElementById('budget_max');
  var fill = document.getElementById('budgetFill');
  var tMin = document.getElementById('budgetMinTxt');
  var tMax = document.getElementById('budgetMaxTxt');
  var LO = parseInt(min.min, 10), HI = parseInt(min.max, 10);

  function render(){
    var a = parseInt(min.value, 10), b = parseInt(max.value, 10);
    // Le minimum ne peut jamais dépasser le maximum
    if (a > b){
      if (document.activeElement === min) { b = a; max.value = a; }
      else { a = b; min.value = b; }
    }
    tMin.textContent = euroFmt(a, HI);
    tMax.textContent = euroFmt(b, HI);
    var p1 = ((a - LO) / (HI - LO)) * 100;
    var p2 = ((b - LO) / (HI - LO)) * 100;
    fill.style.left  = p1 + '%';
    fill.style.width = (p2 - p1) + '%';
  }

  min.addEventListener('input', render);
  max.addEventListener('input', render);
  render();
}

/* ---------- Aperçu d'image avant envoi ---------- */
function previewImg(input, targetId){
  var img = document.getElementById(targetId);
  if (!img || !input.files || !input.files[0]) return;
  var r = new FileReader();
  r.onload = function(ev){ img.src = ev.target.result; img.classList.add('on'); };
  r.readAsDataURL(input.files[0]);
}
