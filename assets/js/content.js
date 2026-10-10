'use strict';
(() => {
 const article=document.querySelector('.legal-copy'),toc=document.getElementById('legal-nav');if(!article||!toc)return;
 article.querySelectorAll('h2').forEach((heading,index)=>{heading.id=`section-${index+1}`;const link=document.createElement('a');link.href=`#${heading.id}`;link.textContent=heading.textContent;toc.append(link);});
})();
