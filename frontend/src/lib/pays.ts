/**
 * Tous les pays du monde (195 reconnus par l'ONU) avec leurs villes principales
 * (capitales + grandes villes). La saisie libre reste autorisée via CitySelect
 * au cas où une ville ne serait pas listée.
 *
 * Tri : par continent puis par nom de pays en français.
 */

export interface Pays {
  code: string        // code ISO 3166-1 alpha-2
  nom: string         // nom en français
  drapeau: string     // emoji drapeau
  villes: string[]
}

// Capitales + grandes villes par pays (non exhaustif mais très complet).
// Format : [code, nom, drapeau, [capitales + villes...]]
const RAW: [string, string, string, string[]][] = [
  // ─────────── AFRIQUE ───────────
  ['DZ','Algérie','🇩🇿',['Alger','Oran','Constantine','Annaba','Blida','Batna','Djelfa','Sétif','Sidi Bel Abbès','Biskra','Tébessa','Tlemcen','Béjaïa','Tiaret','Ouargla','Skikda']],
  ['AO','Angola','🇦🇴',['Luanda','Huambo','Lobito','Benguela','Kuito','Lucapa','Malanje','Namibe','Soyo','Sumbe','Uige','Cabinda','Lubango']],
  ['BJ','Bénin','🇧🇯',['Porto-Novo','Cotonou','Parakou','Bohicon','Abomey-Calavi','Ouidah','Djougou','Natitingou','Lokossa','Malanville','Kandi','Savalou','Save','Comè','Grand-Popo','Pobè']],
  ['BW','Botswana','🇧🇼',['Gaborone','Francistown','Molepolole','Maun','Selebi-Phikwe','Serowe','Kanye','Mahalapye','Mogoditshane','Bobonong','Palapye','Kasane','Lobatse']],
  ['BF','Burkina Faso','🇧🇫',['Ouagadougou','Bobo-Dioulasso','Koudougou','Banfora','Ouahigouya','Dédougou','Tenkodogo','Kaya','Fada N\'Gourma','Koupéla','Dori','Gaoua','Pô']],
  ['BI','Burundi','🇧🇮',['Gitega','Bujumbura','Muyinga','Ruyigi','Ngozi','Rutana','Bururi','Makamba','Muramvya','Cibitoke']],
  ['CV','Cap-Vert','🇨🇻',['Praia','Mindelo','Santa Maria','Assomada','Tarrafal','Pedra Badejo','São Filipe','Mosteiros']],
  ['CM','Cameroun','🇨🇲',['Yaoundé','Douala','Garoua','Bamenda','Bafoussam','Maroua','Kribi','Limbé','Kumba','Nkongsamba','Buea','Edéa','Ngaoundéré','Bertoua','Ebolowa','Mokolo']],
  ['CF','République centrafricaine','🇨🇫',['Bangui','Bimbo','Berbérati','Carnot','Bambari','Bouar','Bossangoa','Nola','Bangassou','Mbaïki','Bria','Kaga-Bandoro']],
  ['KM','Comores','🇰🇲',['Moroni','Mutsamudu','Fomboni','Domoni','Tsimbeo','Nioumadzaha','Ouani','Mbéni']],
  ['CG','Congo-Brazzaville','🇨🇬',['Brazzaville','Pointe-Noire','Dolisie','Nkayi','Ouesso','Madingou','Impfondo','Sibiti','Loandjili','Kinkala']],
  ['CD','RD Congo','🇨🇩',['Kinshasa','Lubumbashi','Goma','Bukavu','Kisangani','Matadi','Mbuji-Mayi','Kolwezi','Kananga','Likasi','Tshikapa','Kikwit','Mbandaka','Boma','Butembo','Uvira']],
  ['CI','Côte d\'Ivoire','🇨🇮',['Yamoussoukro','Abidjan','Bouaké','Daloa','San-Pédro','Korhogo','Man','Gagnoa','Divo','Soubré','Anyama','Abengourou','Agboville','Grand-Bassam','Dabakala']],
  ['DJ','Djibouti','🇩🇯',['Djibouti','Ali Sabieh','Tadjourah','Obock','Dikhil','Arta','Holhol','Gâlâfi']],
  ['EG','Égypte','🇪🇬',['Le Caire','Alexandrie','Gizeh','Shubra El-Kheima','Port-Saïd','Suez','Louxor','Assouan','Mansourah','Tanta','Ismaïlia','Fayoum','Zagazig','Hurghada','Damiette']],
  ['GQ','Guinée équatoriale','🇬🇶',['Malabo','Bata','Ebebiyin','Aconibe','Añisoc','Luba','Evinayong','Mongomo','Rebola','Mbiné']],
  ['ER','Érythrée','🇪🇷',['Asmara','Keren','Massawa','Assab','Mendefera','Barentu','Adi Keyh','Decamere','Nakfa','Tesseney']],
  ['SZ','Eswatini','🇸🇿',['Mbabane','Lobamba','Manzini','Big Bend','Malkerns','Nhlangano','Siteki','Piggs Peak']],
  ['ET','Éthiopie','🇪🇹',['Addis-Abeba','Dire Dawa','Mekele','Adama','Gondar','Awasa','Bahir Dar','Jimma','Dessie','Shashamane','Bishoftu','Arba Minch','Harar','Jijiga','Sodo']],
  ['GA','Gabon','🇬🇦',['Libreville','Port-Gentil','Franceville','Oyem','Moanda','Mouila','Lambaréné','Tchibanga','Koulamoutou','Ndjolé','Gamba','Makokou']],
  ['GM','Gambie','🇬🇲',['Banjul','Serrekunda','Brikama','Bakau','Farafenni','Lamin','Kerewan','Mansa Konko','Janjanbureh','Basse Santa Su']],
  ['GH','Ghana','🇬🇭',['Accra','Kumasi','Tamale','Sekondi-Takoradi','Ashaiman','Tema','Obuasi','Ho','Cape Coast','Teshie','Madina','Wa','Sunyani','Techiman','Koforidua']],
  ['GN','Guinée','🇬🇳',['Conakry','Kankan','Nzérékoré','Kindia','Labé','Guéckédou','Kissidougou','Mamou','Siguiri','Boké','Kindia','Forécariah']],
  ['GW','Guinée-Bissau','🇬🇼',['Bissau','Bafatá','Gabú','Bissorã','Buba','Cacheu','Catió','Mansôa','Fulacunda','Farim','Bolama']],
  ['KE','Kenya','🇰🇪',['Nairobi','Mombasa','Kisumu','Nakuru','Eldoret','Ruiru','Thika','Kitale','Malindi','Garissa','Machakos','Kakamega','Kericho','Nyeri','Meru']],
  ['LS','Lesotho','🇱🇸',['Maseru','Teyateyaneng','Mafeteng','Hlotse','Mohale\'s Hoek','Maputsoe','Quthing','Qacha\'s Nek','Butha-Buthe']],
  ['LR','Libéria','🇱🇷',['Monrovia','Gbarnga','Buchanan','Ganta','Kakata','Harbel','Zwedru','Harper','Voinjama','Pleebo','Robertsport']],
  ['LY','Libye','🇱🇾',['Tripoli','Benghazi','Misrata','Tarhounah','Zaouïa','Zliten','Tobrouk','Syrte','Sabratha','Homs','Derna','Sebha','El-Beida','Ajdabiya']],
  ['MG','Madagascar','🇲🇬',['Antananarivo','Toamasina','Antsirabe','Fianarantsoa','Mahajanga','Toliara','Antsiranana','Ambovombe','Mananjary','Nosy Be','Morondava','Ambilobe','Tulear']],
  ['MW','Malawi','🇲🇼',['Lilongwe','Blantyre','Mzuzu','Zomba','Kasungu','Karonga','Mangochi','Salima','Liwonde','Balaka','Dedza','Nkhata Bay']],
  ['ML','Mali','🇲🇱',['Bamako','Sikasso','Ségou','Mopti','Tombouctou','Kayes','Gao','Koutiala','Kati','San','Sikasso','Djenné','Nioro du Sahel']],
  ['MR','Mauritanie','🇲🇷',['Nouakchott','Nouadhibou','Rosso','Kaédi','Kiffa','Zouérat','Atar','Bogué','Sélibaby','Aleg','Akjoujt','Tidjikja']],
  ['MU','Maurice','🇲🇺',['Port-Louis','Beau Bassin-Rose Hill','Vacoas-Phoenix','Curepipe','Quatre Bornes','Mahebourg','Goodlands','Centre de Flacq','Triolet','Souillac']],
  ['MA','Maroc','🇲🇦',['Rabat','Casablanca','Fès','Marrakech','Tanger','Agadir','Meknès','Oujda','Kenitra','Tétouan','Safi','Salé','Béni Mellal','El Jadida','Taza','Nador','Khouribga','Mohammedia','Essaouira','Chefchaouen']],
  ['MZ','Mozambique','🇲🇿',['Maputo','Matola','Beira','Nampula','Chimoio','Nacala','Quelimane','Tete','Xai-Xai','Maxixe','Pemba','Lichinga','Gurúè','Inhambane']],
  ['NA','Namibie','🇳🇦',['Windhoek','Walvis Bay','Rundu','Swakopmund','Oshakati','Rehoboth','Otjiwarongo','Keetmanshoop','Tsumeb','Gobabis','Lüderitz','Oranjemund']],
  ['NE','Niger','🇳🇪',['Niamey','Zinder','Maradi','Agadez','Tahoua','Dosso','Tillabéri','Diffa','Arlit','Kollo','Téra']],
  ['NG','Nigeria','🇳🇬',['Abuja','Lagos','Kano','Ibadan','Port Harcourt','Benin City','Enugu','Kaduna','Onitsha','Calabar','Abeokuta','Uyo','Maiduguri','Ilorin','Jos','Aba','Warri','Owerri','Sokoto','Katsina','Zaria','Ogbomoso']],
  ['UG','Ouganda','🇺🇬',['Kampala','Gulu','Lira','Mbarara','Jinja','Bwizibwera','Mbale','Entebbe','Kasese','Masaka','Njeru','Kitgum','Soroti','Arua']],
  ['RW','Rwanda','🇷🇼',['Kigali','Butare','Gitarama','Ruhengeri','Gisenyi','Cyangugu','Kibuye','Byumba','Cyangugu','Rwamagana','Muhanga','Musanze']],
  ['ST','São Tomé-et-Príncipe','🇸🇹',['São Tomé','Trindade','Santana','Neves','Santo António','Pantufo','Guadalupe','Santa Cruz']],
  ['SN','Sénégal','🇸🇳',['Dakar','Touba','Thiès','Rufisque','Kaolack','Saint-Louis','Ziguinchor','Mbour','Diourbel','Louga','Tambacounda','Kolda','Richard-Toll','Mbacké','Fatick']],
  ['SC','Seychelles','🇸🇨',['Victoria','Anse Boileau','Anse Royale','Au Cap','Beau Vallon','Bel Ombre','Cascade','Glacis','Pointe La Rue']],
  ['SL','Sierra Leone','🇸🇱',['Freetown','Bo','Kenema','Koidu','Makeni','Waterloo','Lunsar','Port Loko','Kabala','Kailahun','Moyamba','Pujehun']],
  ['SO','Somalie','🇸🇴',['Mogadiscio','Hargeisa','Bosaso','Galkayo','Berbera','Kismayo','Merca','Baidoa','Burao','Borama','Garowe','Jowhar']],
  ['ZA','Afrique du Sud','🇿🇦',['Pretoria','Le Cap','Bloemfontein','Johannesburg','Durban','Port Elizabeth','East London','Soweto','Pretoria','Polokwane','Nelspruit','Kimberley','Rustenburg','Germiston','Boksburg','Cape Town','Pietermaritzburg','Vereeniging','Welkom']],
  ['SS','Soudan du Sud','🇸🇸',['Djouba','Yei','Wau','Malakal','Nimule','Rumbek','Bor','Torit','Kapoeta','Juba','Awiel','Aweil']],
  ['SD','Soudan','🇸🇩',['Khartoum','Omdourman','Khartoum-Nord','Port-Soudan','Kassala','Al-Ubayyid','Gedaref','Wad Madani','Al-Fashir','El-Obeid','Kosti','Sennar','Nyala','Atbara']],
  ['TZ','Tanzanie','🇹🇿',['Dodoma','Dar es Salaam','Mwanza','Zanzibar','Arusha','Mbeya','Morogoro','Tanga','Kigoma','Moshi','Tabora','Dodoma','Songea','Iringa','Shinyanga','Mtwara']],
  ['TD','Tchad','🇹🇩',['N\'Djaména','Moundou','Abéché','Sarh','Kélo','Koumra','Pala','Am Timan','Bongor','Mongo','Doba','Ati','Faya-Largeau']],
  ['TG','Togo','🇹🇬',['Lomé','Sokodé','Kara','Kpalimé','Atakpamé','Tsévié','Dapaong','Bassar','Anié','Tchamba','Notsé','Sotouboua','Vogan']],
  ['TN','Tunisie','🇹🇳',['Tunis','Sfax','Sousse','Kairouan','Bizerte','Gabès','Ariana','Gafsa','Monastir','La Marsa','Ben Arous','Nabeul','Tataouine','Tozeur','Hammamet','Sidi Bou Saïd']],
  ['ZM','Zambie','🇿🇲',['Lusaka','Kitwe','Ndola','Kabwe','Chingola','Mufulira','Livingstone','Luanshya','Kasama','Chipata','Solwezi','Mongu','Mazabuka','Choma']],
  ['ZW','Zimbabwe','🇿🇼',['Harare','Bulawayo','Chitungwiza','Mutare','Gweru','Epworth','Kwekwe','Kadoma','Masvingo','Chinhoyi','Marondera','Norton']],

  // ─────────── AMÉRIQUES ───────────
  ['AG','Antigua-et-Barbuda','🇦🇬',['Saint John\'s','All Saints','Liberta','Potter\'s Village','Bolans','Swetes','Pigotts','Codrington','Parham']],
  ['BS','Bahamas','🇧🇸',['Nassau','Freeport','West End','Coopers Town','Marsh Harbour','Freetown','Dunmore Town','Rock Sound','George Town']],
  ['BB','Barbade','🇧🇧',['Bridgetown','Speightstown','Oistins','Holetown','Crane','Bathsheba','Crab Hill','Blackmans']],
  ['BZ','Belize','🇧🇿',['Belmopan','Belize City','San Ignacio','Orange Walk','Belmopan','Dangriga','Corozal','Punta Gorda','Benque Viejo del Carmen']],
  ['CA','Canada','🇨🇦',['Ottawa','Toronto','Montréal','Vancouver','Calgary','Edmonton','Québec','Winnipeg','Hamilton','Kitchener','London','Halifax','Victoria','Mississauga','Brampton','Surrey','Laval','Gatineau','Longueuil','Saskatoon','Regina','St. John\'s','Fredericton','Charlottetown','Whitehorse','Yellowknife','Iqaluit']],
  ['CR','Costa Rica','🇨🇷',['San José','Alajuela','Cartago','Heredia','Liberia','Puntarenas','Limón','Pérez Zeledón','San Francisco','San Vicente']],
  ['CU','Cuba','🇨🇺',['La Havane','Santiago de Cuba','Camagüey','Holguín','Guantánamo','Santa Clara','Bayamo','Cienfuegos','Pinar del Río','Matanzas','Ciego de Ávila','Manzanillo','Sancti Spíritus','Las Tunas']],
  ['DM','Dominique','🇩🇲',['Roseau','Portsmouth','Marigot','Berekua','Soufrière','Saint Joseph','Pointe Michel','Castle Bruce','Mahaut','La Plaine']],
  ['DO','Rép. dominicaine','🇩🇴',['Saint-Domingue','Santiago de los Caballeros','La Vega','Puerto Plata','San Pedro de Macorís','La Romana','Los Alcarrizos','San Francisco de Macorís','Moca','Higüey','Santo Domingo Este','Bani']],
  ['SV','El Salvador','🇸🇻',['San Salvador','Santa Ana','Soyapango','San Miguel','Mejicanos','Apopa','Delgado','Sonsonate','San Marcos','Usulután']],
  ['US','États-Unis','🇺🇸',['Washington','New York','Los Angeles','Chicago','Houston','Phoenix','Philadelphie','San Antonio','San Diego','Dallas','San José','Austin','Jacksonville','Fort Worth','Columbus','Indianapolis','Charlotte','San Francisco','Seattle','Denver','Nashville','Oklahoma City','El Paso','Boston','Detroit','Portland','Las Vegas','Memphis','Louisville','Baltimore','Milwaukee','Albuquerque','Tucson','Fresno','Miami','Atlanta','Sacramento','Kansas City','Colorado Springs','Mesa','Virginia Beach','Raleigh','Omaha','Minneapolis','Tulsa','Oakland','Cleveland','Wichita','Arlington','Tampa','New Orleans','Honolulu','Anchorage','Salt Lake City']],
  ['GD','Grenade','🇬🇩',['Saint-Georges','Grenville','Gouyave','Victoria','Sauteurs','Saint David\'s','Hillsborough']],
  ['GT','Guatemala','🇬🇹',['Guatemala','Villa Nueva','Mixco','Quetzaltenango','Escuintla','Puerto Barrios','Chiquimula','Retalhuleu','Mazatenango','Huehuetenango','Cobán','Chimaltenango']],
  ['HT','Haïti','🇭🇹',['Port-au-Prince','Carrefour','Delmas','Cap-Haïtien','Pétion-Ville','Port-de-Paix','Jacmel','Gonaïves','Léogâne','Les Cayes','Jérémie','Hinche']],
  ['HN','Honduras','🇭🇳',['Tegucigalpa','San Pedro Sula','Choloma','La Ceiba','El Progreso','Choluteca','Comayagua','Puerto Cortés','La Lima','Danlí']],
  ['JM','Jamaïque','🇯🇲',['Kingston','Spanish Town','Montego Bay','Mandeville','May Pen','Old Harbour','Savanna-la-Mar','Ocho Rios','Port Antonio','St. Ann\'s Bay']],
  ['MX','Mexique','🇲🇽',['Mexico','Guadalajara','Monterrey','Puebla','Tijuana','Ciudad Juárez','León','Zapopan','Cancún','Mérida','Querétaro','Morelia','Acapulco','Veracruz','Toluca','Chihuahua','Saltillo','Aguascalientes','Hermosillo','Culiacán','San Luis Potosí','Torreón','Oaxaca','Puerto Vallarta']],
  ['NI','Nicaragua','🇳🇮',['Managua','León','Granada','Tipitapa','Masaya','Matagalpa','Chinandega','Estelí','Jinotepe','Rivas','Puerto Cabezas','Bluefields']],
  ['PA','Panama','🇵🇦',['Panama','Colón','David','Santiago de Veraguas','La Chorrera','Chitré','Penonomé','Bocas del Toro','Las Tablas','Aguadulce']],
  ['KN','Saint-Christophe-et-Niévès','🇰🇳',['Basseterre','Charlestown','Sandy Point Town','Dieppe Bay Town','Cayon','Monkey Hill','Fig Tree','Gingerland']],
  ['LC','Sainte-Lucie','🇱🇨',['Castries','Vieux Fort','Soufrière','Micoud','Dennery','Gros Islet','Anse La Raye','Laborie','Choiseul','Canaries']],
  ['VC','Saint-Vincent-et-les-Grenadines','🇻🇨',['Kingstown','Georgetown','Byera Village','Layou','Barrouallie','Port Elizabeth','Ashton','Port Elizabeth']],
  ['TT','Trinité-et-Tobago','🇹🇹',['Port-d\'Espagne','Chaguanas','San Fernando','Arima','Marabella','Point Fortin','Tunapuna','Couva','Scarborough','Diego Martin']],

  // ─── AMÉRIQUE DU SUD ───
  ['AR','Argentine','🇦🇷',['Buenos Aires','Córdoba','Rosario','Mendoza','San Miguel de Tucumán','La Plata','Mar del Plata','Salta','Resistencia','Santa Fe','Corrientes','Posadas','Bahía Blanca','San Salvador de Jujuy','Neuquén','Paraná','Formosa','San Luis','Comodoro Rivadavia','Ushuaia']],
  ['BO','Bolivie','🇧🇴',['Sucre','La Paz','Santa Cruz de la Sierra','Cochabamba','El Alto','Oruro','Potosí','Tarija','Sucre','Cobija','Trinidad','Riberalta','Montero','Quillacollo']],
  ['BR','Brésil','🇧🇷',['Brasília','São Paulo','Rio de Janeiro','Salvador','Fortaleza','Belo Horizonte','Manaus','Curitiba','Recife','Porto Alegre','Belém','Goiânia','Guarulhos','Campinas','São Luís','Maceió','Natal','Teresina','Campo Grande','Cuiabá','Florianópolis','João Pessoa','Aracaju','Vitória']],
  ['CL','Chili','🇨🇱',['Santiago','Valparaíso','Concepción','La Serena','Antofagasta','Temuco','Rancagua','Talca','Arica','Chillán','Iquique','Punta Arenas','Valdivia','Osorno','Calama','Copiapó','Vina del Mar']],
  ['CO','Colombie','🇨🇴',['Bogota','Medellín','Cali','Barranquilla','Cartegena','Cúcuta','Bucaramanga','Pereira','Santa Marta','Ibagué','Manizales','Pasto','Neiva','Villavicencio','Montería','Armenia','Valledupar','Popayán','Sincelejo']],
  ['EC','Équateur','🇪🇨',['Quito','Guayaquil','Cuenca','Santo Domingo','Machala','Manta','Portoviejo','Duran','Ambato','Riobamba','Loja','Esmeraldas','Quevedo','Milagro','Ibarra','Tulcán']],
  ['GY','Guyana','🇬🇾',['Georgetown','Linden','New Amsterdam','Anna Regina','Bartica','Corriverton','Rose Hall','Skeldon','Mahdia','Lethem','Mabaruma']],
  ['PY','Paraguay','🇵🇾',['Asuncion','Ciudad del Este','San Lorenzo','Luque','Capiatá','Lambare','Fernando de la Mora','Encarnación','Mariano Roque Alonso','Pedro Juan Caballero','Villarrica','Caacupé','Concepción']],
  ['PE','Pérou','🇵🇪',['Lima','Arequipa','Trujillo','Chiclayo','Piura','Chimbote','Iquitos','Cusco','Huancayo','Tacna','Juliaca','Ica','Cajamarca','Pucallpa','Sullana','Ayacucho','Chincha Alta','Huánuco']],
  ['SR','Suriname','🇸🇷',['Paramaribo','Lelydorp','Nieuw Nickerie','Moengo','Nieuw Amsterdam','Marienburg','Wageningen','Groningen','Totness']],
  ['UY','Uruguay','🇺🇾',['Montevideo','Salto','Ciudad de la Costa','Paysandú','Las Piedras','Rivera','Maldonado','Tacuarembó','Melo','Mercedes','Artigas','Minas']],
  ['VE','Venezuela','🇻🇪',['Caracas','Maracaibo','Valencia','Barquisimeto','Maracay','Ciudad Guayana','San Cristóbal','Maturín','Mérida','Barcelona','Ciudad Bolívar','Cumaná','Barinas','Coro','Cabimas','Puerto La Cruz','Guanare']],

  // ─────────── ASIE ───────────
  ['AF','Afghanistan','🇦🇫',['Kaboul','Hérat','Kandahar','Mazar-i-Sharif','Kunduz','Djalalabad','Lashkar Gah','Taloqan','Puli Khumri','Charikar','Ghazni','Sheberghan','Maymana','Maidan Shar']],
  ['AM','Arménie','🇦🇲',['Erevan','Gyumri','Vanadzor','Vagharshapat','Hrazdan','Abovyan','Kapan','Ararat','Armavir','Gavar','Artashat','Ijevan']],
  ['AZ','Azerbaïdjan','🇦🇿',['Bakou','Gandja','Sumgayit','Mingačevir','Qaraçuxur','Shirvan','Nakhitchevan','Lankaran','Sheki','Yevlakh','Khankendi','Barda','Khirdalan']],
  ['BH','Bahreïn','🇧🇭',['Manama','Riffa','Muharraq','Hamad Town','A\'ali','Isa Town','Sitra','Budaiya','Jidhafs','Al-Malikiyah']],
  ['BD','Bangladesh','🇧🇩',['Dacca','Chittagong','Khulna','Rajshahi','Rangpur','Sylhet','Barisal','Comilla','Narayanganj','Mymensingh','Gopalganj','Bogra','Tongi','Nawabganj']],
  ['BT','Bhoutan','🇧🇹',['Thimphu','Phuntsholing','Punakha','Jakar','Wangdue Phodrang','Paro','Samdrup Jongkhar','Gelephu','Trongsa','Mongar','Trashigang']],
  ['MM','Birmanie','🇲🇲',['Naypyidaw','Rangoon','Mandalay','Nay Pyi Taw','Mawlamyine','Bago','Pathein','Monywa','Sittwe','Meiktila','Myeik','Taunggyi','Myitkyina','Tavoy']],
  ['BN','Brunei','🇧🇳',['Bandar Seri Begawan','Kuala Belait','Seria','Tutong','Bangar','Jerudong','Muara','Kampong Ayer']],
  ['KH','Cambodge','🇰🇭',['Phnom Penh','Sihanoukville','Battambang','Siem Reap','Poipet','Preah Sihanouk','Ta Khmau','Kampong Cham','Kampot','Takeo','Kratie','Koh Kong']],
  ['CN','Chine','🇨🇳',['Pékin','Shanghai','Guangzhou','Shenzhen','Chengdu','Wuhan','Xi\'an','Tianjin','Hangzhou','Suzhou','Nankin','Chongqing','Shenyang','Qingdao','Zhengzhou','Dongguan','Hong Kong','Harbin','Hefei','Fuzhou','Changsha','Nanning','Kunming','Dalian','Xiamen','Jinan','Ürümqi','Lhasa','Macao']],
  ['CY','Chypre','🇨🇾',['Nicosie','Limassol','Larnaca','Famagouste','Paphos','Kyrenia','Protaras','Paralimni','Aradhippou','Morphou']],
  ['KP','Corée du Nord','🇰🇵',['Pyongyang','Hamhung','Chongjin','Nampo','Wonsan','Sinuiju','Tanchon','Kaechon','Kaesong','Sariwon','Haeju','Kanggye','Hyesan','Kimchaek']],
  ['KR','Corée du Sud','🇰🇷',['Séoul','Busan','Incheon','Daegu','Daejeon','Gwangju','Suwon','Ulsan','Jeonju','Changwon','Seongnam','Cheongju','Cheonan','Pohang','Jeju','Goyang','Bucheon','Gimhae']],
  ['AE','Émirats arabes unis','🇦🇪',['Abou Dabi','Dubaï','Charjah','Al Aïn','Ajman','Ras el Khaïmah','Fujairah','Umm al-Qaïwain','Khor Fakkan','Dibba Al-Fujairah']],
  ['GE','Géorgie','🇬🇪',['Tbilissi','Koutaïssi','Batoumi','Roustavi','Zougdidi','Gori','Poti','Soukhoumi','Tskhinvali','Kobuleti','Akhaltsikhe']],
  ['IN','Inde','🇮🇳',['New Delhi','Bombay','Delhi','Bangalore','Hyderabad','Ahmedabad','Chennai','Calcutta','Surat','Pune','Jaipur','Lucknow','Kanpur','Nagpur','Indore','Bhopal','Patna','Vadodara','Agra','Varanasi','Ludhiana','Coimbatore','Madurai','Visakhapatnam','Thiruvananthapuram','Kochi','Goa','Mumbai']],
  ['ID','Indonésie','🇮🇩',['Jakarta','Surabaya','Bandung','Medan','Bekasi','Palembang','Tangerang','Makassar','Semarang','Depok','Padang','Denpasar','Bogor','Pekanbaru','Malang','Balikpapan','Pontianak','Batam','Yogyakarta','Banda Aceh','Mataram','Manado']],
  ['IR','Iran','🇮🇷',['Téhéran','Machhad','Ispahan','Karaj','Chiraz','Tabriz','Qom','Ahvaz','Kermanchah','Urmia','Racht','Zahedan','Hamedan','Kerman','Yazd','Ardabil','Bandar Abbas','Sari','Arak']],
  ['IQ','Irak','🇮🇶',['Bagdad','Mossoul','Bassora','Erbil','Kirkouk','Souleimaniye','Nadjaf','Karbala','Nassiriya','Amara','Diyarbakır','Ramadi','Al Fallujah','Tikrit','Samawa']],
  ['IL','Israël','🇮🇱',['Jérusalem','Tel Aviv','Haïfa','Rishon LeZion','Petah Tikva','Ashdod','Netanya','Beer-Sheva','Bnei Brak','Holon','Ramat Gan','Eilat','Ashkelon']],
  ['JP','Japon','🇯🇵',['Tokyo','Yokohama','Osaka','Nagoya','Sapporo','Fukuoka','Kobe','Kyoto','Kawasaki','Saitama','Hiroshima','Sendai','Kitakyushu','Chiba','Sakai','Niigata','Hamamatsu','Shizuoka','Kumamoto','Okayama','Nagasaki','Kagoshima','Nara','Okinawa']],
  ['JO','Jordanie','🇯🇴',['Amman','Zarqa','Irbid','Russeifa','Ar Ramtha','Aqaba','Madaba','Mafraq','Ma\'an','Karak','Jerash','Tafilah','Salt','Ajloun']],
  ['KZ','Kazakhstan','🇰🇿',['Astana','Almaty','Chimkent','Karaganda','Taraz','Aktobe','Pavlodar','Oskemen','Semey','Oral','Kostanaï','Kyzylorda','Atyrau','Kokshetau','Taldykourgan']],
  ['KG','Kirghizistan','🇰🇬',['Bichkek','Och','Djalal-Abad','Karakol','Tokmok','Kara-Balta','Balykchy','Naryn','Uzgen','Kyzyl-Kiya','Batken','Talas']],
  ['KW','Koweït','🇰🇼',['Koweït','Al Ahmadi','Hawalli','As-Salimiya','Sabah as-Salim','Al Farwaniyah','Al Fahahil','Kuwait City','Al Jahra','Khaitan']],
  ['LA','Laos','🇱🇦',['Vientiane','Savannakhet','Pakse','Luang Prabang','Xam Neua','Thakhek','Phonsavan','Vang Vieng','Muang Xay','Huay Xai','Sainyabuli','Salavan']],
  ['LB','Liban','🇱🇧',['Beyrouth','Tripoli','Sidon','Tyr','Jounieh','Zahleh','Zgharta','Byblos','Baalbek','Nabatieh','Batroun','Jbeil']],
  ['MY','Malaisie','🇲🇾',['Kuala Lumpur','George Town','Ipoh','Johor Bahru','Shah Alam','Malacca','Kota Kinabalu','Kuching','Petaling Jaya','Alor Setar','Kota Bharu','Seremban','Miri','Sibu','Klang','Muar']],
  ['MV','Maldives','🇲🇻',['Malé','Addu City','Fuvahmulah','Kulhudhuffushi','Thinadhoo','Naifaru','Maafushi','Hithadhoo']],
  ['MN','Mongolie','🇲🇳',['Oulan-Bator','Erdenet','Darkhan','Choibalsan','Mörön','Khovd','Ölgii','Dalanzadgad','Bayankhongor','Sainshand','Ulaangom']],
  ['NP','Népal','🇳🇵',['Katmandou','Pokhara','Lalitpur','Biratnagar','Birgunj','Dharan','Bharatpur','Bhimdatta','Butwal','Nepalgunj','Hetauda','Dhangadhi','Janakpur']],
  ['OM','Oman','🇴🇲',['Mascate','Salalah','Sohar','Nizwa','Sur','Ibri','Khasab','Rustaq','Barka','Al Buraimi','Seeb','Shinas']],
  ['PK','Pakistan','🇵🇰',['Islamabad','Karachi','Lahore','Faisalabad','Rawalpindi','Multan','Hyderabad','Gujranwala','Peshawar','Quetta','Sargodha','Sialkot','Bahawalpur','Sukkur','Larkana','Sheikhupura','Mardan','Gujrat']],
  ['PS','Palestine','🇵🇸',['Jérusalem-Est','Gaza','Hébron','Naplouse','Rafah','Khan Younès','Jénine','Bethléem','Ramallah','Tulkarem','Beit Jala','Jéricho']],
  ['PH','Philippines','🇵🇭',['Manille','Quezon City','Davao','Cebu City','Caloocan','Zamboanga','Taguig','Antipolo','Cagayan de Oro','Parañaque','Dasmariñas','Bacolod','Mandaluyong','Makati','Pasig','Iloilo','Baguio','Batangas','Cotabato','Angeles','Baguio','Puerto Princesa']],
  ['QA','Qatar','🇶🇦',['Doha','Ar Rayyan','Umm Salal','Al Wakrah','Al Khor','Al Rayyan','Madinat ash Shamal','Dukhan','Mesaieed','Al Khawr']],
  ['SA','Arabie saoudite','🇸🇦',['Riyad','Djeddah','La Mecque','Médine','Dammam','Taëf','Tabuk','Buraydah','Khamis Mushait','Hail','Najran','Jubail','Abha','Khobar','Yanbu','Qatif','Al-Hofuf']],
  ['SG','Singapour','🇸🇬',['Singapour','Jurong','Woodlands','Tampines','Kallang','Bedok','Sengkang','Bukit Batok','Clementi','Yishun','Serangoon','Bishan','Ang Mo Kio','Toa Payoh','Punggol']],
  ['LK','Sri Lanka','🇱🇰',['Sri Jayawardenapura Kotte','Colombo','Kandy','Galle','Jaffna','Negombo','Kurunegala','Matara','Ratnapura','Anuradhapura','Batticaloa','Trincomalee','Nuwara Eliya']],
  ['SY','Syrie','🇸🇾',['Damas','Alep','Homs','Lattaquié','Hama','Raqqa','Deir ez-Zor','Idlib','Al-Hasakah','Deraa','Tartous','Qamichli','Suwayda','Palmyre']],
  ['TJ','Tadjikistan','🇹🇯',['Douchanbé','Khujand','Kulob','Bokhtar','Tursunzoda','Istaravshan','Vahdat','Isfara','Khorugh','Panjakent','Tursunzade','Konibodom']],
  ['TW','Taïwan','🇹🇼',['Taipei','Kaohsiung','Taichung','Tainan','Banqiao','Hsinchu','Keelung','Taoyuan','Zhongli','Hualien','Changhua','Pingtung','Chiayi']],
  ['TH','Thaïlande','🇹🇭',['Bangkok','Nonthaburi','Nakhon Ratchasima','Chiang Mai','Hat Yai','Udon Thani','Pak Kret','Surat Thani','Khon Kaen','Pattaya','Nakhon Sawan','Ubon Ratchathani','Phuket','Rayong','Songkhla','Chiang Rai']],
  ['TL','Timor oriental','🇹🇱',['Dili','Baucau','Maliana','Suai','Same','Manatuto','Lospalos','Viqueque','Aileu','Maubisse','Liquiçá','Pante Macassar']],
  ['TR','Turquie','🇹🇷',['Ankara','Istanbul','Izmir','Bursa','Adana','Gaziantep','Konya','Antalya','Kayseri','Mersin','Eskişehir','Diyarbakır','Samsun','Denizli','Şanlıurfa','Trabzon','Malatya','Erzurum','Van','Alanya','Bodrum','Trabzon']],
  ['TM','Turkménistan','🇹🇲',['Achgabat','Turkmenabat','Daşoguz','Mary','Balkanabat','Baýramaly','Türkmenbaşy','Tejen','Serdar','Magdanly','Gumdag']],
  ['UZ','Ouzbékistan','🇺🇿',['Tachkent','Samarcande','Namangan','Andijan','Nukus','Ferghana','Boukhara','Qarshi','Kokand','Margilan','Urgench','Djizak','Termez','Navoiy']],
  ['VN','Viêt Nam','🇻🇳',['Hanoï','Hô Chi Minh-Ville','Haïphong','Da Nang','Cần Thơ','Bien Hoa','Nha Trang','Buon Ma Thuot','Hue','Vũng Tàu','Thái Nguyên','Hải Dương','Huế','Long Xuyên','Vinh','Rach Gia','Quy Nhơn','Phan Thiết']],
  ['YE','Yémen','🇾🇪',['Sanaa','Aden','Taëz','Hodeïda','Ibb','Al-Mukalla','Dhamar','Amran','Zinjibar','Sayyan','Hajjah','Mukalla','Zabid']],

  // ─────────── EUROPE ───────────
  ['AL','Albanie','🇦🇱',['Tirana','Durrës','Vlorë','Elbasan','Shkodër','Fier','Korçë','Berat','Lushnjë','Kamëz','Pogradec','Kavajë','Gjirokastër','Sarandë']],
  ['DE','Allemagne','🇩🇪',['Berlin','Hambourg','Munich','Cologne','Francfort-sur-le-Main','Stuttgart','Düsseldorf','Leipzig','Dortmund','Essen','Brême','Dresde','Hanovre','Nuremberg','Duisbourg','Bochum','Wuppertal','Bonn','Mannheim','Karlsruhe','Münster','Wiesbaden','Augsbourg','Mönchengladbach','Gelsenkirchen','Brunswick','Kiel','Chemnitz','Aix-la-Chapelle','Halle','Magdebourg','Fribourg-en-Brisgau','Krefeld','Mayence','Lübeck','Erfurt','Rostock','Cassel']],
  ['AD','Andorre','🇦🇩',['Andorre-la-Vieille','Escaldes-Engordany','Encamp','Sant Julià de Lòria','La Massana','Santa Coloma','Ordino','Canillo','Pas de la Casa','El Tarter']],
  ['AT','Autriche','🇦🇹',['Vienne','Graz','Linz','Salzbourg','Innsbruck','Klagenfurt','Villach','Wels','Sankt Pölten','Dornbirn','Wiener Neustadt','Steyr','Feldkirch','Bregenz','Eisenstadt']],
  ['BE','Belgique','🇧🇪',['Bruxelles','Anvers','Gand','Charleroi','Liège','Bruges','Schaerbeek','Anderlecht','Namur','Louvain','Mons','Malines','Ixelles','Uccle','La Louvière','Tournai','Kortrijk','Hasselt','Saint-Nicolas','Ostende']],
  ['BY','Biélorussie','🇧🇾',['Minsk','Gomel','Mogilev','Vitebsk','Grodno','Brest','Babrouïsk','Baranavitchy','Baryssaw','Pinsk','Orcha','Mazyr','Novopolotsk','Salihorsk']],
  ['BA','Bosnie-Herzégovine','🇧🇦',['Sarajevo','Banja Luka','Tuzla','Zenica','Mostar','Bihać','Bugojno','Brod','Brčko','Bijeljina','Prijedor','Trebinje','Doboj','Cazin','Zvornik']],
  ['BG','Bulgarie','🇧🇬',['Sofia','Plovdiv','Varna','Bourgas','Roussé','Stara Zagora','Pleven','Sliven','Dobritch','Choumen','Pernik','Haskovo','Yambol','Pazardjik','Blagoevgrad','Vratsa','Veliko Tarnovo']],
  ['HR','Croatie','🇭🇷',['Zagreb','Split','Rijeka','Osijek','Zadar','Slavonski Brod','Pula','Sesvete','Karlovac','Varaždin','Šibenik','Sisak','Velika Gorica','Dubrovnik','Vinkovci','Vukovar','Koprivnica','Čakovec']],
  ['DK','Danemark','🇩🇰',['Copenhague','Aarhus','Odense','Aalborg','Esbjerg','Randers','Kolding','Horsens','Vejle','Roskilde','Herning','Helsingør','Næstved','Silkeborg','Frederiksberg','Gentofte','Gladsaxe']],
  ['ES','Espagne','🇪🇸',['Madrid','Barcelone','Valence','Séville','Saragosse','Malaga','Murcie','Palma','Las Palmas de Grande Canarie','Bilbao','Alicante','Córdoue','Valladolid','Vigo','Gijón','Hospitalet de Llobregat','Vitoria-Gasteiz','Grenade','La Corogne','Pampelune','Badajoz','Tolède','Salamanque','Saint-Sébastien','Cadix','Tarragone','Oviedo','Burgos','Santander','Castellón de la Plana','Ibiza','Marbella','Séville']],
  ['EE','Estonie','🇪🇪',['Tallinn','Tartu','Narva','Pärnu','Kohtla-Järve','Viljandi','Rakvere','Sillamäe','Maardu','Kuressaare','Võru','Valga','Haapsalu','Jõhvi']],
  ['FI','Finlande','🇫🇮',['Helsinki','Espoo','Tampere','Vantaa','Turku','Oulu','Jyväskylä','Lahti','Kuopio','Pori','Kouvola','Rovaniemi','Joensuu','Lappeenranta','Hämeenlinna','Vaasa','Seinäjoki','Rovaniemi']],
  ['FR','France','🇫🇷',['Paris','Marseille','Lyon','Toulouse','Nice','Nantes','Montpellier','Strasbourg','Bordeaux','Lille','Rennes','Reims','Le Havre','Saint-Étienne','Toulon','Grenoble','Dijon','Angers','Nîmes','Villeurbanne','Saint-Denis','Aix-en-Provence','Brest','Le Mans','Amiens','Tours','Limoges','Clermont-Ferrand','Annecy','Besançon','Metz','Perpignan','Orléans','Rouen','Mulhouse','Caen','Nancy','Avignon','Poitiers','Pau','La Rochelle','Calais','Cherbourg','Biarritz','Cannes','Nantes','Lorient','Valenciennes','Cergy-Pontoise','Antibes','Saint-Malo','Colmar','Strasbourg']],
  ['GR','Grèce','🇬🇷',['Athènes','Thessalonique','Patras','Le Pirée','Héraklion','Larissa','Volos','Rhodes','Ioannina','Chania','Chalcis','Katerini','Agrínio','Serres','Xanthi','Kavala','Kalamata','Alexandroupoli']],
  ['HU','Hongrie','🇭🇺',['Budapest','Debrecen','Szeged','Miskolc','Pécs','Győr','Nyíregyháza','Kecskemét','Székesfehérvár','Szombathely','Érd','Sopron','Veszprém','Zalaegerszeg','Eger','Nagykanizsa','Dunaújváros']],
  ['IE','Irlande','🇮🇪',['Dublin','Cork','Limerick','Galway','Waterford','Drogheda','Dundalk','Swords','Bray','Navan','Kilkenny','Ennis','Carlow','Tralee','Naas','Sligo','Mullingar','Letterkenny','Wexford']],
  ['IS','Islande','🇮🇸',['Reykjavik','Kópavogur','Hafnarfjörður','Akureyri','Garðabær','Mosfellsbær','Árborg','Akranes','Fjarðabyggð','Reykjanesbær','Selfoss','Ísafjörður','Vestmannaeyjar']],
  ['IT','Italie','🇮🇹',['Rome','Milan','Naples','Turin','Palerme','Gênes','Bologne','Florence','Bari','Catane','Venise','Vérone','Messine','Padoue','Trieste','Brescia','Tarente','Prato','Parme','Reggio de Calabre','Modène','Reggio d\'Émilie','Pérouse','Livourne','Ravenne','Cagliari','Foggia','Rimini','Salerne','Ferrare','Sassari','Syracuse','Pescara','Monza','Bergame','Trente','Vicence','Arezzo','Ancône','Brescia']],
  ['XK','Kosovo','🇽🇰',['Pristina','Prizren','Gjilan','Peja','Mitrovica','Ferizaj','Gjakova','Vushtrri','Podujevë','Mitrovica e Re','Kosovska Mitrovica','Suva Reka']],
  ['LV','Lettonie','🇱🇻',['Riga','Daugavpils','Liepāja','Jelgava','Jūrmala','Ventspils','Rēzekne','Ogre','Valmiera','Jēkabpils','Tukums','Salaspils','Cēsis']],
  ['LI','Liechtenstein','🇱🇮',['Vaduz','Schaan','Triesen','Triesenberg','Balzers','Eschen','Mauren','Ruggell','Gamprin','Planken','Schellenberg']],
  ['LT','Lituanie','🇱🇹',['Vilnius','Kaunas','Klaipėda','Šiauliai','Panevėžys','Alytus','Marijampolė','Mažeikiai','Jonava','Utena','Kėdainiai','Telšiai','Tauragė','Ukmergė','Visaginas']],
  ['LU','Luxembourg','🇱🇺',['Luxembourg','Esch-sur-Alzette','Differdange','Dudelange','Ettelbruck','Diekirch','Wiltz','Rumelange','Grevenmacher','Echternach','Bettembourg','Mersch','Berschbach']],
  ['MK','Macédoine du Nord','🇲🇰',['Skopje','Bitola','Kumanovo','Prilep','Tetovo','Veles','Štip','Ohrid','Gostivar','Kavadarci','Kočani','Kičevo','Strumica','Radoviš']],
  ['MT','Malte','🇲🇹',['La Valette','Birkirkara','Mosta','Qormi','Żabbar','Sliema','San Ġwann','Fgura','Żejtun','Naxxar','Paola','Rabat','Marsaskala','Attard']],
  ['MD','Moldavie','🇲🇩',['Chișinău','Tiraspol','Bălți','Tighina (Bender)','Rîbnița','Cahul','Ungheni','Soroca','Orhei','Dubăsari','Comrat','Strășeni','Edineț','Drochia']],
  ['MC','Monaco','🇲🇨',['Monaco','Monte-Carlo','La Condamine','Fontvieille','Moneghetti','Larvotto','Saint Roman','Le Portier']],
  ['ME','Monténégro','🇲🇪',['Podgorica','Nikšić','Pljevlja','Bijelo Polje','Cetinje','Bar','Herceg Novi','Berane','Budva','Ulcinj','Tivat','Kotor','Danilovgrad','Rožaje']],
  ['NO','Norvège','🇳🇴',['Oslo','Bergen','Trondheim','Stavanger','Sandvika','Drammen','Fredrikstad','Kristiansand','Tromsø','Sarpsborg','Skien','Sandnes','Asker','Bærum','Ålesund','Tønsberg','Moss','Haugesund','Molde','Bodø']],
  ['NL','Pays-Bas','🇳🇱',['Amsterdam','Rotterdam','La Haye','Utrecht','Eindhoven','Groningue','Tilburg','Almere','Bréda','Nimègue','Arnhem','Haarlem','Enschede','Apeldoorn','Zaanstad','Arnhem','Bois-le-Duc','Amersfoort','Dordrecht','Zutphen','Maastricht','Leiden','Zwolle','Delft','La Haye','Leyde']],
  ['PL','Pologne','🇵🇱',['Varsovie','Cracovie','Łódź','Wrocław','Poznań','Gdańsk','Szczecin','Bydgoszcz','Lublin','Katowice','Białystok','Gdynia','Częstochowa','Radom','Sosnowiec','Toruń','Kielce','Rzeszów','Gliwice','Zabrze','Olsztyn','Bielsko-Biała','Bytom','Ruda Śląska','Rybnik','Tychy','Opole']],
  ['PT','Portugal','🇵🇹',['Lisbonne','Porto','Vila Nova de Gaia','Amadora','Braga','Coimbra','Agualva-Cacém','Setúbal','Queluz','Funchal','Almada','Cacém','Aveiro','Guimarães','Leiria','Viseu','Évora','Faro','Portimão','Ponta Delgada','Bragança','Braga','Coimbra']],
  ['RO','Roumanie','🇷🇴',['Bucarest','Cluj-Napoca','Timișoara','Iași','Constanța','Craiova','Brașov','Galați','Ploiești','Oradea','Brăila','Arad','Pitești','Sibiu','Bacău','Târgu Mureș','Baia Mare','Buzău','Botoșani','Satu Mare','Suceava','Târgu Jiu','Piatra Neamț','Drobeta-Turnu Severin']],
  ['GB','Royaume-Uni','🇬🇧',['Londres','Birmingham','Manchester','Glasgow','Liverpool','Leeds','Sheffield','Édimbourg','Bristol','Cardiff','Belfast','Leicester','Coventry','Nottingham','Hull','Newcastle upon Tyne','Stoke-on-Trent','Southampton','Derby','Portsmouth','Wolverhampton','Plymouth','Bradford','Sunderland','Brighton','Reading','Swansea','Luton','Bournemouth','Norwich','Aberdeen','Cambridge','Oxford','York','Exeter','Bath','Inverness','Canterbury','Winchester']],
  ['RU','Russie','🇷🇺',['Moscou','Saint-Pétersbourg','Novossibirsk','Iekaterinbourg','Kazan','Tcheliabinsk','Omsk','Samara','Rostov-sur-le-Don','Oufa','Krasnoïarsk','Voronej','Perm','Volgograd','Krasnodar','Saratov','Tioumen','Togliatti','Khabarovsk','Irkoutsk','Vladivostok','Novgorod','Kalinine','Iaroslavl','Khabarovsk','Barnaul','Vladikavkaz','Ijevsk','Tomsk','Nijni Novgorod']],
  ['SM','Saint-Marin','🇸🇲',['Saint-Marin','Serravalle','Borgo Maggiore','Domagnano','Fiorentino','Acquaviva','Faetano','Chiesanuova','Montegiardino']],
  ['RS','Serbie','🇷🇸',['Belgrade','Novi Sad','Niš','Kragujevac','Subotica','Zrenjanin','Pančevo','Čačak','Kraljevo','Leskovac','Užice','Novi Pazar','Valjevo','Vranje','Smederevo','Šabac']],
  ['SK','Slovaquie','🇸🇰',['Bratislava','Košice','Prešov','Žilina','Nitra','Banská Bystrica','Trnava','Martin','Trenčín','Poprad','Prievidza','Zvolen','Považská Bystrica','Michalovce','Nové Zámky','Spišská Nová Ves','Komárno','Levice']],
  ['SI','Slovénie','🇸🇮',['Ljubljana','Maribor','Celje','Kranj','Koper','Velenje','Novo Mesto','Ptuj','Trbovlje','Kamnik','Jesenice','Nova Gorica','Kočevje']],
  ['SE','Suède','🇸🇪',['Stockholm','Göteborg','Malmö','Uppsala','Västerås','Örebro','Linköping','Helsingborg','Jönköping','Norrköping','Lund','Umeå','Gävle','Borås','Eskilstuna','Södertälje','Karlstad','Täby','Växjö','Halmstad','Sundsvall','Luleå','Trollhättan','Östersund','Visby']],
  ['CH','Suisse','🇨🇭',['Berne','Zurich','Genève','Bâle','Lausanne','Winterthour','Lucerne','Saint-Gall','Lugano','Bienne','Fribourg','Neuchâtel','Thoune','Bellinzone','Coire','Köniz','Uster','Schaffhouse','Vernier','Yverdon-les-Bains','Sion','Montreux']],
  ['CZ','Tchéquie','🇨🇿',['Prague','Brno','Ostrava','Plzeň','Liberec','Olomouc','České Budějovice','Hradec Králové','Ústí nad Labem','Pardubice','Zlín','Havířov','Kladno','Most','Opava','Frýdek-Místek','Karviná','Karlovy Vary','Jihlava','Teplice','Děčín']],
  ['UA','Ukraine','🇺🇦',['Kiev','Kharkiv','Odessa','Dnipro','Donetsk','Zaporijjia','Lviv','Kryvyï Rih','Mykolaïv','Marioupol','Sébastopol','Luhansk','Vinnytsia','Tchernihiv','Kherson','Poltava','Tcherkassy','Khmelnytskyï','Tchernivtsi','Jytomyr','Soumy','Rivne','Ivano-Frankivsk','Ternopil','Kropyvnytskyï']],
  ['VA','Vatican','🇻🇦',['Cité du Vatican']],

  // ─────────── OCÉANIE ───────────
  ['AU','Australie','🇦🇺',['Canberra','Sydney','Melbourne','Brisbane','Perth','Adélaïde','Gold Coast','Newcastle','Canberra','Wollongong','Hobart','Geelong','Townsville','Cairns','Toowoomba','Darwin','Ballarat','Bendigo','Albury','Launceston','Mackay','Rockhampton','Bunbury','Bundaberg','Sunshine Coast']],
  ['FJ','Fidji','🇫🇯',['Suva','Lautoka','Nadi','Labasa','Ba','Levuka','Sigatoka','Rakiraki','Nausori','Nasinu','Savusavu','Taveuni']],
  ['KI','Kiribati','🇰🇮',['Tarawa-Sud','Betio','Bikenibeu','Teaoraereke','Bairiki','Temaraia','Ambo','Banaba','London','Tabwakea']],
  ['MH','Îles Marshall','🇲🇭',['Majuro','Ebeye','Laura','Ajeltake','Delap-Uliga-Djarrit','Rairok','Jabor','Wotje']],
  ['FM','Micronésie','🇫🇲',['Palikir','Weno','Kolonia','Tofol','Tafunsak','Tol','Tomil','Sapwalap','Madolenihmw','Kitti']],
  ['NR','Nauru','🇳🇷',['Yaren','Denigomodu','Aiwo','Meneng','Anabar','Anibare','Baiti','Boe','Buada','Ewa','Ijuw','Nibok','Uaboe','Yadua']],
  ['NZ','Nouvelle-Zélande','🇳🇿',['Wellington','Auckland','Wellington','Christchurch','Hamilton','Tauranga','Dunedin','Palmerston North','Napier','Rotorua','New Plymouth','Nelson','Whangarei','Invercargill','Gisborne','Lower Hutt','Porirua','Upper Hutt','Whanganui','Tauranga','Taupō']],
  ['PW','Palaos','🇵🇼',['Ngerulmud','Koror','Airai','Melekeok','Meyuns','Ngchesar','Ngiwal','Ngatpang','Ngardmau','Kayangel']],
  ['PG','Papouasie-Nouvelle-Guinée','🇵🇬',['Port Moresby','Lae','Arawa','Mount Hagen','Popondetta','Madang','Kokopo','Kimbe','Wewak','Goroka','Rabaul','Kavieng','Alotau','Daru','Vanimo','Kundiawa']],
  ['WS','Samoa','🇼🇸',['Apia','Asau','Mulifanua','Faleula','Le\'a\'a-fa\'i\'a\'i','Vailele','Lufilufi','Safotu','Gataivai','Salelologa']],
  ['SB','Îles Salomon','🇸🇧',['Honiara','Auki','Gizo','Buala','Kirakira','Lata','Tulagi','Taro','Munda','Noro','Ringgi']],
  ['TO','Tonga','🇹🇴',['Nuku\'alofa','Neiafu','Pangai','Haveluloto','Vaini','Ohonua','‘Ohonua','Mu\'a','Tatakamotonga','Kolonga']],
  ['TV','Tuvalu','🇹🇻',['Funafuti','Vaiaku','Alapi','Lofeagai','Senala','Teone','Amatuku','Fakaifou']],
  ['VU','Vanuatu','🇻🇺',['Port-Vila','Luganville','Norsup','Isangel','Lakatoro','Saratamata','Sola','Bunlap','Lenakel']],
]

// Déduplique les villes tout en conservant la capitale en premier.
function dedupe(villes: string[]): string[] {
  const vues = new Set<string>()
  const out: string[] = []
  for (const v of villes) {
    const k = v.trim().toLowerCase()
    if (!k || vues.has(k)) continue
    vues.add(k)
    out.push(v.trim())
  }
  return out
}

export const PAYS: Pays[] = RAW.map(([code, nom, drapeau, villes]) => ({
  code,
  nom,
  drapeau,
  villes: dedupe(villes),
}))

// Index rapide
const BY_CODE: Record<string, Pays> = {}
const BY_NOM: Record<string, Pays> = {}
for (const p of PAYS) {
  BY_CODE[p.code.toLowerCase()] = p
  BY_NOM[p.nom.toLowerCase()] = p
}

export function trouverPays(nomOuCode?: string | null): Pays | undefined {
  if (!nomOuCode) return undefined
  const s = nomOuCode.trim().toLowerCase()
  if (!s) return undefined
  return BY_CODE[s] || BY_NOM[s]
}

export function villesDuPays(nomOuCode?: string | null): string[] {
  const p = trouverPays(nomOuCode)
  return p ? p.villes : []
}
