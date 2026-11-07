import {readFileSync} from 'node:fs';
const root=new URL('../../public/assets/js/',import.meta.url);
const surface='data:text/javascript;base64,'+readFileSync(new URL('patch-studio-surface.js',root)).toString('base64');
const source=readFileSync(new URL('patch-studio-core.js',root),'utf8').replace("await import(new URL('./patch-studio-surface.js' + new URL(import.meta.url).search, import.meta.url))",`await import(${JSON.stringify(surface)})`);
export default await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
