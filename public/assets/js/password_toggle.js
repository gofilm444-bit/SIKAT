(function(){
  function shouldSkip(input){
    if (!input || input.dataset.pwToggle === '1') return true;
    if (input.dataset.pwToggleSkip === '1') return true;
    var parent = input.parentElement;
    if (!parent) return true;
    if (parent.querySelector('.pw-toggle-btn')) return true;
    return false;
  }

  function enhance(input){
    if (shouldSkip(input)) { return; }
    var parent = input.parentElement;
    if (!parent) { return; }

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle-btn';
    btn.setAttribute('aria-label', 'Tampilkan password');
    btn.textContent = 'lihat';

    input.classList.add('pw-toggle-input');
    parent.classList.add('pw-toggle-wrap');
    if (getComputedStyle(parent).position === 'static') {
      parent.style.position = 'relative';
    }

    btn.addEventListener('click', function(){
      var showing = (input.type === 'text');
      input.type = showing ? 'password' : 'text';
      btn.textContent = showing ? 'lihat' : 'sembunyi';
      btn.setAttribute('aria-label', showing ? 'Tampilkan password' : 'Sembunyikan password');

      input.classList.remove('pw-bounce','pw-shake');
      btn.classList.remove('pw-wink','pw-wiggle');
      if (!showing) {
        input.classList.add('pw-bounce');
        btn.classList.add('pw-wink');
      } else {
        input.classList.add('pw-shake');
        btn.classList.add('pw-wiggle');
      }
      setTimeout(function(){
        input.classList.remove('pw-bounce','pw-shake');
        btn.classList.remove('pw-wink','pw-wiggle');
      }, 350);
    });

    parent.appendChild(btn);
    input.dataset.pwToggle = '1';
  }

  function scan(root){
    var scope = root || document;
    var inputs = scope.querySelectorAll('input[type="password"]');
    inputs.forEach(enhance);
  }

  document.addEventListener('DOMContentLoaded', function(){
    scan(document);
  });

  var observer = new MutationObserver(function(mutations){
    mutations.forEach(function(m){
      m.addedNodes.forEach(function(node){
        if (node.nodeType !== 1) return;
        if (node.matches && node.matches('input[type="password"]')) {
          enhance(node);
        }
        if (node.querySelectorAll) {
          node.querySelectorAll('input[type="password"]').forEach(enhance);
        }
      });
    });
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
