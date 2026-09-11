(function(){
  const root=document.documentElement;
  const toggle=document.querySelector('[data-theme-toggle]');
  const apply=t=>{root.setAttribute('data-theme',t);try{localStorage.setItem('pkc-theme',t);}catch(e){}};
  if(toggle){
    toggle.addEventListener('click',()=>apply(root.getAttribute('data-theme')==='dark'?'light':'dark'));
  }
  const burger=document.querySelector('[data-menu-toggle]');
  const mobile=document.querySelector('[data-mobile-nav]');
  if(burger&&mobile){
    burger.addEventListener('click',()=>{
      const open=mobile.classList.toggle('is-open');
      mobile.hidden=!open;
      burger.setAttribute('aria-expanded',open?'true':'false');
    });
  }
  const header=document.querySelector('[data-header]');
  const book=document.querySelector('[data-book]');
  if(book){
    window.addEventListener('scroll',()=>{
      if(window.matchMedia('(prefers-reduced-motion:reduce)').matches)return;
      const y=Math.min(window.scrollY/4,80);
      book.style.transform=`translateY(${y*0.15}px) rotate(${y*0.05}deg)`;
    },{passive:true});
  }
  if(header){
    window.addEventListener('scroll',()=>{header.classList.toggle('is-stuck',window.scrollY>8);},{passive:true});
  }
  const api=(path,opts={})=>{
    const headers=Object.assign({'Content-Type':'application/json','X-PKC-Nonce':(window.pkcData&&pkcData.nonce)||''},opts.headers||{});
    return fetch((window.pkcData&&pkcData.rest||'')+path,{...opts,headers,credentials:'same-origin'}).then(async r=>{
      const data=await r.json().catch(()=>({}));
      if(!r.ok) throw new Error(data.message||'Request failed');
      return data;
    });
  };
  window.pkcApi=api;
  const msg=(el,text,ok)=>{if(!el)return;el.hidden=false;el.textContent=text;el.className='pkc-form-msg '+(ok?'pkc-alert-ok':'pkc-alert');};

  const contact=document.querySelector('[data-contact-form]');
  if(contact){
    contact.addEventListener('submit',e=>{
      e.preventDefault();
      const fd=new FormData(contact);
      api('contact',{method:'POST',body:JSON.stringify(Object.fromEntries(fd))})
        .then(d=>msg(contact.querySelector('[data-form-msg]'),d.message,true))
        .catch(err=>msg(contact.querySelector('[data-form-msg]'),err.message,false));
    });
  }
  const coupon=document.querySelector('[data-coupon]');
  if(coupon){
    const out=document.querySelector('[data-coupon-out]');
    const email=document.querySelector('input[name="email"]');
    const run=()=>{
      const code=coupon.value.trim();
      if(!code){return;}
      api('coupons/validate',{method:'POST',body:JSON.stringify({code,course_id:coupon.dataset.course,email:email?email.value:''})})
        .then(d=>{if(out)out.innerHTML='Amount due: <strong>'+d.formatted_total+'</strong> (discount applied)';})
        .catch(err=>{if(out)out.textContent=err.message;});
    };
    coupon.addEventListener('blur',run);
  }
  const method=document.querySelector('[data-method]');
  const help=document.querySelector('[data-method-help]');
  if(method&&help){
    method.addEventListener('change',()=>{
      const opt=method.selectedOptions[0];
      help.textContent=opt&&opt.dataset.ins?opt.dataset.ins:'';
    });
  }
  const forgot=document.querySelector('[data-forgot-form]');
  if(forgot){
    forgot.addEventListener('submit',e=>{
      e.preventDefault();
      api('auth/forgot',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(forgot)))})
        .then(d=>msg(forgot.querySelector('[data-form-msg]'),d.message,true))
        .catch(err=>msg(forgot.querySelector('[data-form-msg]'),err.message,false));
    });
  }
  const reset=document.querySelector('[data-reset-form]');
  if(reset){
    reset.addEventListener('submit',e=>{
      e.preventDefault();
      api('auth/reset',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(reset)))})
        .then(d=>{msg(reset.querySelector('[data-form-msg]'),d.message,true);if(d.redirect)location.href=d.redirect;})
        .catch(err=>msg(reset.querySelector('[data-form-msg]'),err.message,false));
    });
  }
})();
