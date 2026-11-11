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
 const adminSource=fs.readFileSync('public/admin/assets/admin.js','utf8');
 const adminElements={};
 const adminElement=()=>({value:'',innerHTML:'',hidden:false,textContent:'',classList:{remove(){},add(){},toggle(){}},querySelectorAll:()=>[],showModal(){},reset(){}});
 const fields={};for(const name of ['id','kode','nama','kategori','harga','min_pesan','kuota_default','gambar','gambar_file'])fields[name]=adminElement();
 adminElements['#product-form']=Object.assign(adminElement(),{elements:fields});
 adminElements['#quota-date']=Object.assign(adminElement(),{value:'2030-11-20'});
 const adminContext={console,FormData,URL,setTimeout:()=>{},clearTimeout(){},document:{querySelector:s=>adminElements[s]??=adminElement(),querySelectorAll:()=>[]}};
 vm.createContext(adminContext);
 vm.runInContext(adminSource.slice(0,adminSource.lastIndexOf('\nshell();')),adminContext);
 vm.runInContext("api=async()=>({items:[]});refreshProducts=()=>{};loadKuotaHarian=async()=>{};toast=()=>{};",adminContext);
 adminContext.editProduct({id:1,kode:'A',nama:'Menu',kategori:'makanan',harga:10000,min_pesan:4,kuota_default:60,kuota:30});
 assert.equal(fields.kuota_default.value,60);
 adminContext.editProduct({id:1,kode:'A',nama:'Menu',kategori:'makanan',harga:10000,min_pesan:4,kuota_default:0,kuota:30});
 assert.equal(fields.kuota_default.value,0);
 adminContext.editProduct(null);assert.equal(fields.kuota_default.value,0);
 adminContext.productsPage();
 adminElements['#kuota-nilai']??=adminElement();
 fields.id.value='1';adminElements['#kuota-tanggal'].value='2030-11-20';adminElements['#kuota-tanggal-selesai'].value='2030-11-25';adminElements['#kuota-nilai'].value='0';
 vm.runInContext("api=async(url,options)=>{globalThis.sent=options.body;return {ok:true}}",adminContext);
 await adminElements['#kuota-add'].onclick();
 assert.equal(adminContext.sent.tanggal_mulai,'2030-11-20');assert.equal(adminContext.sent.tanggal_selesai,'2030-11-25');assert.equal(adminContext.sent.kuota,0);
 await adminElements['#kuota-reset'].onclick();assert.equal(adminContext.sent.action,'reset');
 console.log('PASS: Form Admin memakai default, mempertahankan nilai nol, dan mengirim save/reset rentang');
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
