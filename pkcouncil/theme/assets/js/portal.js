(function(){
  const api=window.pkcApi;
  if(!api)return;
  const msg=(form,text,ok)=>{const el=form.querySelector('[data-form-msg]');if(!el)return;el.hidden=false;el.textContent=text;el.className='pkc-form-msg '+(ok?'pkc-alert-ok':'pkc-alert');};
  document.querySelectorAll('[data-profile-form]').forEach(form=>{
    form.addEventListener('submit',e=>{
      e.preventDefault();
      api('me',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(form)))})
        .then(()=>msg(form,'Profile saved.',true))
        .catch(err=>msg(form,err.message,false));
    });
  });
  document.querySelectorAll('[data-password-form]').forEach(form=>{
    form.addEventListener('submit',e=>{
      e.preventDefault();
      api('me/password',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(form)))})
        .then(d=>msg(form,d.message,true))
        .catch(err=>msg(form,err.message,false));
    });
  });
  document.querySelectorAll('[data-complete]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const art=btn.closest('[data-progress]');
      api('progress',{method:'POST',body:JSON.stringify({course_id:art.dataset.course,lesson_id:art.dataset.lesson,event:'lesson_completed'})})
        .then(d=>{btn.textContent='Completed · '+d.percent+'%';});
    });
  });
  document.querySelectorAll('[data-chat-form]').forEach(form=>{
    form.addEventListener('submit',e=>{
      e.preventDefault();
      const wrap=form.closest('[data-chat]');
      const body={course_id:wrap.dataset.course,body:form.body.value};
      if(wrap.dataset.student)body.student_id=wrap.dataset.student;
      api('chat',{method:'POST',body:JSON.stringify(body)}).then(d=>{
        const log=wrap.querySelector('[data-chat-log]');
        const div=document.createElement('div');
        div.className='pkc-bubble pkc-bubble--'+(window.pkcData.role||'student');
        div.innerHTML='<p></p><time></time>';
        div.querySelector('p').textContent=form.body.value;
        div.querySelector('time').textContent=d.created_at||'now';
        log.appendChild(div);
        form.reset();
        log.scrollTop=log.scrollHeight;
      }).catch(err=>alert(err.message));
    });
  });
})();
