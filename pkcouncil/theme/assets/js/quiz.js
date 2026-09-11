(function(){
  const root=document.getElementById('pkc-quiz');
  if(!root||!window.pkcApi)return;
  const startBtn=root.querySelector('[data-start]');
  const form=root.querySelector('[data-quiz-form]');
  const timerEl=root.querySelector('[data-timer]');
  let attemptId=0,remaining=0,tick=null,submitting=false;
  const fmt=s=>String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
  const render=questions=>{
    form.innerHTML='';
    questions.forEach((q,i)=>{
      const wrap=document.createElement('fieldset');
      wrap.className='pkc-q';
      const legend=document.createElement('legend');
      legend.textContent=(i+1)+'. ';
      const span=document.createElement('span');
      span.innerHTML=q.question;
      legend.appendChild(span);
      wrap.appendChild(legend);
      q.options.forEach(o=>{
        const lab=document.createElement('label');
        lab.style.display='block';
        const inp=document.createElement('input');
        inp.type='radio';inp.name='q'+q.id;inp.value=o.id;
        inp.addEventListener('change',()=>{
          window.pkcApi('quiz/answer',{method:'POST',body:JSON.stringify({attempt_id:attemptId,question_id:q.id,option_id:o.id})}).catch(()=>{});
        });
        lab.appendChild(inp);
        lab.append(' '+o.option_text);
        wrap.appendChild(lab);
      });
      form.appendChild(wrap);
    });
    const btn=document.createElement('button');
    btn.className='pkc-btn pkc-btn--gold';btn.type='submit';btn.textContent='Submit quiz';
    form.appendChild(btn);
    form.hidden=false;
  };
  const submit=()=>{
    if(submitting)return;submitting=true;
    window.pkcApi('quiz/submit',{method:'POST',body:JSON.stringify({attempt_id:attemptId})})
      .then(d=>{location.href=d.redirect;})
      .catch(err=>{submitting=false;alert(err.message);});
  };
  const startClock=()=>{
    timerEl.hidden=false;
    const paint=()=>{timerEl.querySelector('strong').textContent=fmt(remaining);};
    paint();
    tick=setInterval(()=>{
      remaining=Math.max(0,remaining-1);
      paint();
      if(remaining<=0){clearInterval(tick);submit();}
    },1000);
    setInterval(()=>{
      window.pkcApi('quiz/heartbeat',{method:'POST',body:JSON.stringify({attempt_id:attemptId})}).then(d=>{
        remaining=d.remaining;
        if(d.expired&&d.redirect){location.href=d.redirect;}
      }).catch(()=>{});
    },15000);
  };
  startBtn.addEventListener('click',()=>{
    startBtn.disabled=true;
    window.pkcApi('quiz/start',{method:'POST',body:JSON.stringify({quiz_id:root.dataset.quiz})}).then(d=>{
      attemptId=d.attempt_id;remaining=d.remaining;render(d.questions);startClock();
    }).catch(err=>{startBtn.disabled=false;alert(err.message);});
  });
  form.addEventListener('submit',e=>{e.preventDefault();submit();});
})();
