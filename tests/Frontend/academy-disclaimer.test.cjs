const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname,'../../public/assets/js/academy.js'),'utf8');
function page({stored,denied=false,valid=true,version='v1'}={}) {
  const writes=[];
  const handlers={};
  const form={addEventListener:(event,handler)=>handlers[event]=handler,reportValidity:()=>valid};
  const button={addEventListener:(event,handler)=>handlers.reopen=handler};
  const dialog={open:false,dataset:{disclaimerVersion:version},showModal(){this.open=true},close(){this.open=false},querySelector:()=>form};
  const document={querySelector:()=>null,querySelectorAll:selector=>selector==='[data-academy-disclaimer]'?[dialog]:selector==='[data-open-disclaimer]'?[button]:[]};
  const localStorage={getItem(){if(denied)throw new Error('Storage denied');return stored},setItem(key,value){if(denied)throw new Error('Storage denied');writes.push([key,value])}};
  vm.runInNewContext(source,{document,localStorage});
  return {dialog,writes,accept:()=>handlers.submit({preventDefault(){},currentTarget:form}),reopen:()=>handlers.reopen()};
}
test('first visit opens the notice and accepting persists the version',()=>{
  const p=page();assert.equal(p.dialog.open,true);p.accept();assert.equal(p.dialog.open,false);assert.deepEqual(p.writes,[['athena:academy:disclaimer:v1','accepted']]);
});
test('acknowledgement hides the automatic modal but manual reopening works',()=>{
  const p=page({stored:'accepted'});assert.equal(p.dialog.open,false);p.reopen();assert.equal(p.dialog.open,true);
});
test('a new version uses a different acknowledgement key',()=>{
  const p=page({version:'v2'});p.accept();assert.equal(p.writes[0][0],'athena:academy:disclaimer:v2');
});
test('blocked localStorage does not break the page or closing the notice',()=>{
  const p=page({denied:true});assert.equal(p.dialog.open,true);assert.doesNotThrow(()=>p.accept());assert.equal(p.dialog.open,false);
});
test('an unchecked notice is not persisted or closed',()=>{
  const p=page({valid:false});p.accept();assert.equal(p.dialog.open,true);assert.equal(p.writes.length,0);
});
