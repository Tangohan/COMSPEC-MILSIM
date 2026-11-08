// Les reglages d'un objet sont repartis en onglets : un champ d'un onglet masque ne peut pas etre rempli par un utilisateur.
// Ce petit adaptateur ouvre l'onglet du champ avant chaque saisie, exactement comme le ferait une personne.
module.exports=function reveal(page){
 for(const method of ['fill','selectOption','check','uncheck']){
  const original=page[method].bind(page);
  page[method]=async(selector,...rest)=>{
   if(typeof selector==='string'&&selector.includes('data-prop'))await page.evaluate(sel=>{
    const section=document.querySelector(sel)?.closest('[data-fabrication-section]');
    if(section&&section.hidden)document.querySelector('[data-property-section="'+section.dataset.fabricationSection+'"]')?.click();
   },selector);
   return original(selector,...rest);
  };
 }
 return page;
};
