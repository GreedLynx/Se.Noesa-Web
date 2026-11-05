// Uji kontrak JavaScript tanpa dependensi browser tambahan.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
(async()=>{
 for(const [file,option] of [['public/admin/assets/admin.js','options'],['public/assets/js/app.js','opt']]){
  const source=fs.readFileSync(file,'utf8');
  const start=source.indexOf('async function api('),end=source.indexOf('\nfunction toast(',start);
  let requested;
  const context={URL,FormData,location:{},page:'products',appPrefix:'../',
    $:()=>({value:'2030-01-11'}),fetch:async(url)=>{requested=url;return{status:200,ok:true,json:async()=>({})}}};
  vm.createContext(context);vm.runInContext(source.slice(start,end),context);
  await context.api('api/products.php?q=menu');
  assert.equal(new URL(requested,'http://localhost/').searchParams.get('tgl_kirim'),'2030-01-11');
  await context.api('api/products.php',{method:'POST',body:{nama:'Menu'}});
  assert.equal(requested,'api/products.php');
  console.log('PASS: '+file+' mengirim tanggal pada laporan GET dan mempertahankan POST');
 }
 const elements={};
 function element(){return{value:'',innerHTML:'',hidden:false,textContent:'',classList:{remove(){},add(){},toggle(){}},querySelectorAll:()=>[]};}
 const context={FormData,URL,setTimeout:()=>{},document:{body:{dataset:{page:'test'},classList:{toggle(){}}},querySelector:s=>elements[s]??=(element()),querySelectorAll:()=>[]},requestAnimationFrame:fn=>fn()};
 vm.createContext(context);vm.runInContext(fs.readFileSync('public/pelanggan/assets/customer.js','utf8'),context);
 elements['#catalog-delivery-date']=Object.assign(element(),{value:'2030-01-11'});
 elements['#catalog-search']=element();
 vm.runInContext("cart={1:4};productCache={1:{id:1,nama:'Menu A',harga:10000,sisa:20}};api=async()=>({items:[],tanggal:'2030-01-11',kitchenRemaining:0,schedule:null});",context);
 await context.loadCatalog();
 assert.equal(vm.runInContext('productCache[1].sisa',context),0);
 assert.equal(vm.runInContext('cart[1]',context),4);
 assert.equal(vm.runInContext('cartTotals().total',context),40000);
 console.log('PASS: Pergantian tanggal memeriksa keranjang dan mempertahankan item tidak tersedia agar dapat dihapus pelanggan');
})().catch(e=>{console.error(e);process.exitCode=1});
