// ATHENA project home, reusable setups and authorized personnel picker.
export function createWorkspace(c){
 const {root,state,esc,json,notify,modal}=c,$=s=>root.querySelector(s);
 const viewer=Number(root.dataset.recovery.split(':').at(-1));let homeRequest=0,memberRequest=0,searchTimer,resumeExport=null;
 const titles={recent:'Dernières créations',mine:'Mes créations',setups:'Mes setups',organization:'Bibliothèque d’organisation',shared:'Partagés avec moi',archives:'Archives',templates:'Créer depuis un modèle'};
 const date=s=>{const d=new Date(String(s).replace(' ','T')+'Z');return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('fr',{day:'numeric',month:'short',year:'numeric'}).format(d);};
 function enterEditor(){state.view='editor';state.hasEditor=true;$('#studio-home').hidden=true;$('#studio-editor').hidden=false;}
 function homeCards(){
  const filter=state.homeFilter||'recent',q=($('#home-search')?.value||'').toLocaleLowerCase('fr');
  const matches=p=>{
   if(!p.name.toLocaleLowerCase('fr').includes(q))return false;
   if(filter==='archives')return p.status!=='active';if(p.status!=='active')return false;
   if(filter==='setups')return p.kind==='setup';if(filter==='organization')return p.visibility==='organization';if(filter==='shared')return !!p.share_role&&Number(p.owner_id)!==viewer;
   return p.kind!=='setup'&&Number(p.owner_id)===viewer;
  };
  let projects=(state.homeProjects||[]).filter(matches);if(filter==='recent')projects=projects.slice(0,12);
  $('#home-results-count').textContent=String(projects.length);
  $('#home-projects').innerHTML=projects.map(p=>`<article class="ps-home-card"><button class="ps-home-cover" ${p.kind==='setup'?`data-use-setup="${p.id}"`:`data-open="${p.id}"`} aria-label="${esc((p.kind==='setup'?'Utiliser ':'Ouvrir ')+p.name)}">${Number(p.has_preview)?`<img loading="lazy" src="${esc(c.api+'/projects/'+p.id+'/preview?v='+p.preview_revision)}" alt="${esc(p.name)}">`:'<span class="ps-cover-mark" aria-hidden="true">A<span>PATCH STUDIO</span></span>'}<span class="ps-home-kind">${p.kind==='setup'?'SETUP':'PATCH'}</span></button><div class="ps-home-card-info"><h3>${esc(p.name)}</h3><p>${date(p.updated_at)} <span>· v${p.revision}</span></p><div class="ps-inline"><button ${p.kind==='setup'?`data-use-setup="${p.id}"`:`data-open="${p.id}"`}>${p.kind==='setup'?'Utiliser le setup':'Ouvrir'}</button><button data-project-options="${p.id}" aria-label="Gérer ${esc(p.name)}">•••</button></div></div></article>`).join('')||`<div class="ps-home-empty"><span aria-hidden="true">＋</span><h3>${q?'Aucun résultat':'Votre prochaine création commence ici'}</h3><p>${filter==='setups'?'Enregistrez les matières, bordures, textes et dimensions d’un patch comme setup pour les réutiliser.':'Choisissez un modèle entièrement modifiable ou ouvrez un canevas vierge.'}</p><button data-action="new">Créer un patch</button></div>`;
 }
 async function showHome(filter='recent'){
  state.view='home';state.homeFilter=filter;$('#modal').close();$('#studio-editor').hidden=true;$('#studio-home').hidden=false;
  const ticket=++homeRequest;
  $('#studio-home').innerHTML=`<aside class="ps-home-sidebar"><div class="ps-home-brand">VOTRE ATELIER<span>ATHENA PATCH STUDIO</span></div><nav aria-label="Collections">${[['recent','Accueil'],['mine','Mes créations'],['setups','Mes setups'],['organization','Organisation'],['shared','Partagés'],['archives','Archives']].map(([k,n])=>`<button data-home-filter="${k}" ${filter===k?'aria-current="page"':''}>${n}</button>`).join('')}</nav><div class="ps-home-sidebar-bottom"><button data-action="import-project">Importer un projet JSON</button><p>Projets privés, versions conservées et accès de votre communauté.</p></div></aside><main class="ps-home-main"><div class="ps-home-welcome"><div><p class="ps-home-eyebrow">DE L’IDÉE AU TERRAIN</p><h2>Donnez forme à votre unité.</h2><p>Retrouvez vos créations et vos setups, ou partez d’une composition éditable.</p></div><div class="ps-home-cta">${state.hasEditor?'<button data-workspace="resume">Reprendre l’édition</button>':''}<button class="ps-primary" data-action="new">＋ Nouveau patch</button></div></div><section class="ps-home-start"><div class="ps-home-section-title"><h3>Créer un patch</h3><span>MODÈLES ÉDITABLES</span></div><div id="home-templates" class="ps-home-templates"><p class="ps-help">Chargement des modèles…</p></div></section><section class="ps-home-recent"><div class="ps-home-section-title"><h3>${titles[filter]||titles.recent} <span id="home-results-count">0</span></h3><input id="home-search" type="search" placeholder="Rechercher une création…" aria-label="Rechercher une création"></div><div id="home-status" role="status">Chargement de votre bibliothèque…</div><div id="home-projects" class="ps-home-projects"></div></section></main>`;
  $('#home-search').oninput=homeCards;
  c.templateCards().then(html=>{if(ticket===homeRequest)$('#home-templates').innerHTML=html;}).catch(c.error);
  try{const {projects}=await json('/projects');if(ticket!==homeRequest)return;state.homeProjects=projects;$('#home-status').hidden=true;homeCards();}
  catch(e){if(ticket!==homeRequest)return;$('#home-status').className='ps-home-error';$('#home-status').innerHTML=`<strong>Bibliothèque indisponible</strong><p>${esc(e.message)}</p>${e.incident?`<small>Référence : ${esc(e.incident)}</small>`:''}<button data-workspace="retry-home">Réessayer</button>`;}
 }
 async function useSetup(id){
  const {project}=await json('/projects/'+id);if(project.kind!=='setup')throw new Error('Ce projet n’est pas un setup.');await c.createFromSetup(project);
 }
 async function setupDialog(){
  modal('Enregistrer un setup',`<div class="ps-setup-layout"><div class="ps-setup-art"><img id="setup-preview" alt="Aperçu du setup"></div><form id="setup-form"><p class="ps-dialog-kicker">UNE BASE RÉUTILISABLE</p><h3>Gardez votre configuration.</h3><p class="ps-help">Matières, format, bordures, textes, variables et calques restent entièrement modifiables.</p><label>Nom du setup<input id="setup-name" required maxlength="120" value="${esc($('#project-name').value||'Mon setup')}"></label><label>Disponibilité<select id="setup-visibility"><option value="private">Moi uniquement</option><option value="organization">Mon organisation</option></select></label><button class="ps-primary" type="submit">Enregistrer le setup</button><p id="setup-status" class="ps-help" role="status"></p></form></div>`,'ps-setup-dialog');
  c.raster({width:512,height:256}).then(png=>{if($('#setup-preview'))$('#setup-preview').src='data:image/png;base64,'+png;}).catch(c.error);
  $('#setup-form').onsubmit=async e=>{
   e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;
   try{const document=c.clone(state.doc),{project}=await json('/projects',{method:'POST',body:JSON.stringify({name:$('#setup-name').value.trim(),document,kind:'setup'})});let p=project;
    if($('#setup-visibility').value==='organization')p=(await json('/projects/'+p.id,{method:'PUT',body:JSON.stringify({revision:Number(p.revision),name:p.name,document,visibility:'organization',status:'active'})})).project;
    await c.savePreview(p,document).catch(()=>{});$('#modal').close();notify('Setup enregistré dans votre bibliothèque.');
   }catch(error){$('#setup-status').textContent=error.message;}finally{button.disabled=false;}
  };
 }
 async function refreshMembers(query=''){
  const ticket=++memberRequest,result=await json('/variables'+(query?'?q='+encodeURIComponent(query):''));
  if(ticket!==memberRequest)return false;state.members=result.members||[];if(!state.membersReady){state.membersReady=true;if(state.members.some(m=>Number(m.userId)===viewer))state.selectedMembers.add(viewer);}return true;
 }
 function drawMemberList(){
  if(!$('#member-list'))return;
  const unit=$('#member-unit').value,rows=state.members.filter(m=>!unit||String(m.unitId)===unit);
  $('#member-list').innerHTML=rows.map(m=>`<label class="ps-member-row"><input type="checkbox" data-member-check="${m.userId}" ${state.selectedMembers.has(Number(m.userId))?'checked':''}><span><strong>${esc(m.label)}</strong><small>${esc(m.values.callsign||'')} ${m.values.unit?'· '+esc(m.values.unit):''}</small></span><button type="button" data-preview-member="${m.userId}" aria-label="Prévisualiser ${esc(m.label)}">Aperçu</button></label>`).join('')||'<p class="ps-help">Aucun membre autorisé ne correspond à cette recherche.</p>';
  $('#member-selection-count').textContent=state.selectedMembers.size+' sélectionné(s)';
 }
 async function memberDialog(){
  resumeExport=$('#export-format')?Object.fromEntries(['export-format','export-variant','export-size','export-background','export-nv-gain','export-nv-contrast'].map(id=>[id,$('#'+id).value])):null;
  modal('Membres ATHENA',`<div class="ps-members-intro"><div><p class="ps-dialog-kicker">VARIABLES DYNAMIQUES</p><p>Choisissez les membres pour la prévisualisation et la génération en série.</p></div><span id="member-selection-count">0 sélectionné(s)</span></div><div class="ps-members-search"><input id="member-query" type="search" placeholder="Nom ou indicatif…" aria-label="Rechercher un membre"><select id="member-unit" aria-label="Filtrer les membres par unité"><option value="">Toutes les unités visibles</option></select></div><div class="ps-inline"><button data-workspace="select-members">Tout sélectionner dans cette vue</button><button data-workspace="clear-members">Effacer la sélection</button></div><div id="member-list" class="ps-member-list" role="status">Chargement des membres autorisés…</div><footer class="ps-dialog-footer"><p class="ps-help">Seuls les membres accessibles avec vos permissions ATHENA apparaissent. Jusqu’à 100 résultats ; affinez la recherche si nécessaire.</p><button class="ps-primary" data-workspace="apply-members">Utiliser la sélection</button></footer>`,'ps-members-dialog');
  const load=async()=>{try{if(!await refreshMembers($('#member-query')?.value||''))return;if(!$('#member-list'))return;const units=new Map(state.members.filter(m=>m.unitId&&m.values.unit).map(m=>[m.unitId,m.values.unit]));const current=$('#member-unit').value;$('#member-unit').innerHTML='<option value="">Toutes les unités visibles</option>'+[...units].map(([id,name])=>`<option value="${id}" ${String(id)===current?'selected':''}>${esc(name)}</option>`).join('');drawMemberList();}catch(e){if($('#member-list'))$('#member-list').textContent=e.message;}};
  $('#member-query').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(load,250);};$('#member-unit').onchange=drawMemberList;await load();
 }
 function applyMember(id){const m=state.members.find(m=>Number(m.userId)===Number(id));if(!m)return;state.memberId=Number(id);state.values=m.values;c.render();}
 root.addEventListener('change',e=>{if(e.target.dataset.memberCheck){const id=Number(e.target.dataset.memberCheck);if(e.target.checked){if(state.selectedMembers.size>=100){e.target.checked=false;notify('100 membres maximum par série.');return;}state.selectedMembers.add(id);}else state.selectedMembers.delete(id);$('#member-selection-count').textContent=state.selectedMembers.size+' sélectionné(s)';}});
 root.addEventListener('click',async e=>{
  const b=e.target.closest('button');if(!b)return;
  try{
   if(b.dataset.homeFilter)await showHome(b.dataset.homeFilter);
   if(b.dataset.useSetup)await useSetup(b.dataset.useSetup);
   if(b.dataset.projectOptions){if(await c.openProject(b.dataset.projectOptions))await c.projectSettings();}
   if(b.dataset.previewMember)applyMember(b.dataset.previewMember);
   if(b.dataset.workspace==='resume'){enterEditor();c.render();c.fit();}
   if(b.dataset.workspace==='retry-home')await showHome(state.homeFilter);
   if(b.dataset.workspace==='select-members'){const unit=$('#member-unit').value;for(const m of state.members.filter(m=>!unit||String(m.unitId)===unit))if(state.selectedMembers.size<100)state.selectedMembers.add(Number(m.userId));drawMemberList();}
   if(b.dataset.workspace==='clear-members'){state.selectedMembers.clear();drawMemberList();}
   if(b.dataset.workspace==='apply-members'){if(state.selectedMembers.size){state.members=(await json('/variables?users='+[...state.selectedMembers].join(','))).members;state.selectedMembers=new Set(state.members.map(m=>Number(m.userId)));const id=state.selectedMembers.has(state.memberId)?state.memberId:[...state.selectedMembers][0];if(id)applyMember(id);}if(resumeExport){const settings=resumeExport;resumeExport=null;await c.exportModal(settings);}else $('#modal').close();}
  }catch(error){c.error(error);}
 });
 return {showHome,enterEditor,setupDialog,memberDialog,refreshMembers};
}
