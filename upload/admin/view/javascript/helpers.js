

/**
 * Create SEO URL string in latin from non-latin string
 * @param {String} str Any string to be converted to URL slug
 * @param {String} lang Optional lang code (e.g. 'en' or 'uk') for some separate replacement rules
 * @returns {String} An URL slug with non-latin characters replaced by latin counterparts and spaces replaced by dashes
 */
function createSeoUrl(str, lang = 'en') {
  const delimiter = '-';

  abc = {' ':'-','ß':'ss','à':'a','á':'a','â':'a','ã':'a','ä':'a','å':'a','æ':'ae','ç':'c','è':'e','é':'e','ê':'e','ë':'e','ì':'i','í':'i','î':'i','ï':'yi','ð':'d','ñ':'n','ò':'o','ó':'o','ô':'o','õ':'o','ö':'o','ő':'o','ø':'o','ù':'u','ú':'u','û':'u','ü':'u','ű':'u','ý':'y','þ':'th','ÿ':'y','α':'a','β':'b','γ':'g','δ':'d','ε':'e','ζ':'z','η':'h','θ':'8','ι':'i','κ':'k','λ':'l','μ':'m','ν':'n','ξ':'3','ο':'o','π':'p','ρ':'r','σ':'s','τ':'t','υ':'y','φ':'f','χ':'x','ψ':'ps','ω':'w','ά':'a','έ':'e','ί':'i','ό':'o','ύ':'y','ή':'h','ώ':'w','ς':'s','ϊ':'i','ΰ':'y','ϋ':'y','ΐ':'i','ş':'s','ı':'i','ç':'c','ü':'u','ö':'o','ğ':'g','а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'j','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','ш':'sh','щ':'sh','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya','є':'ye','і':'i','ї':'yi','ґ':'g','č':'c','ď':'d','ě':'e','ň':'n','ř':'r','š':'s','ť':'t','ů':'u','ž':'z','ą':'a','ć':'c','ę':'e','ł':'l','ń':'n','ó':'o','ś':'s','ź':'z','ż':'z','ā':'a','č':'c','ē':'e','ģ':'g','ī':'i','ķ':'k','ļ':'l','ņ':'n','š':'s','ū':'u','ž':'z','ө':'o','ң':'n','ү':'u','ә':'a','ғ':'g','қ':'q','ұ':'u','ა':'a','ბ':'b','გ':'g','დ':'d','ე':'e','ვ':'v','ზ':'z','თ':'th','ი':'i','კ':'k','ლ':'l','მ':'m','ნ':'n','ო':'o','პ':'p','ჟ':'zh','რ':'r','ს':'s','ტ':'t','უ':'u','ფ':'ph','ქ':'q','ღ':'gh','ყ':'qh','შ':'sh','ჩ':'ch','ც':'ts','ძ':'dz','წ':'ts','ჭ':'tch','ხ':'kh','ჯ':'j','ჰ':'h','&':'-and-'};

  // Separate language settings
  if (lang === 'bg') Object.assign(abc, {'щ': 'sht', 'ъ': 'a'});
  if (lang === 'uk') Object.assign(abc, {'и': 'y', 'г': 'h'});

  // Start transliteration
  str = str.toLowerCase();

  // Replace characters
  const pattern = new RegExp(`[${Object.keys(abc).join('')}]`, 'g');
  str = str.replace(pattern, ch => abc[ch] ?? ch);

  // Remove all non-alpanumeric symbols
  str = str
    .replace(/[^a-z0-9]+/g, delimiter)     // replace everything except latin and nubers
    .replace(new RegExp(`${delimiter}{2,}`, 'g'), delimiter) // remove double dashes
    .replace(new RegExp(`(^${delimiter}|${delimiter}$)`, 'g'), ''); // remove dashes at the end and the beginning

  return str;
}
