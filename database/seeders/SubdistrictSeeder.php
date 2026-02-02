<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Subdistrict;
use Illuminate\Database\Seeder;
use Nnjeim\World\Models\Country;

class SubdistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get Malaysia country ID
        $malaysia = Country::where('iso2', 'MY')->first();

        if (! $malaysia) {
            $this->command->warn('Malaysia country not found. Make sure WorldSeeder has been run.');

            return;
        }

        $subdistrictsByDistrict = $this->getSubdistrictsData();

        foreach ($subdistrictsByDistrict as $districtName => $subdistricts) {
            // Find the district
            $district = District::query()
                ->where('country_id', $malaysia->id)
                ->where('name', $districtName)
                ->first();

            if ($district === null) {
                $this->command->warn("District not found: {$districtName}");

                continue;
            }

            foreach ($subdistricts as $subdistrictName) {
                Subdistrict::query()->updateOrCreate([
                    'district_id' => $district->id,
                    'name' => $subdistrictName,
                ], [
                    'country_id' => $malaysia->id,
                    'state_id' => $district->state_id,
                    'country_code' => 'MY',
                ]);
            }
        }
    }

    /**
     * Get subdistricts data organized by district.
     *
     * @return array<string, array<string>>
     */
    private function getSubdistrictsData(): array
    {
        return [
            'Johor Bahru' => ['Bandar Johor Bahru', 'Bandar Tiram', 'Divisyen Bandaraya', 'Gelang Patah', 'Iskandar Puteri', 'Jelutong', 'Johor Bahru', 'Masai', 'Pasir Gudang', 'Plentong', 'Pulai', 'Sungai Tiram', 'Tanjung Kupang', 'Tebrau', 'Ulu Choh', 'Ulu Tiram'],
            'Batu Pahat' => ['Ayer Hitam', 'Bagan', 'Bandar Penggaram', 'Batu Pahat', 'Chaah Bahru', 'Kampung Bahru', 'Linau', 'Lubok', 'Minyak Beku', 'Parit Raja', 'Parit Sulong', 'Peserai', 'Rengit', 'Semerah', 'Senggarang', 'Seri Gading', 'Seri Medan', 'Simpang Kanan', 'Simpang Kiri', 'Sri Gading', 'Sri Medan', 'Sungai Kluang', 'Sungai Punggor', 'Tanjung Sembrong', 'Yong Peng'],
            'Kluang' => ['Bandar Kluang', 'Chaah', 'Kahang', 'Kluang', 'Layang-Layang', 'Machap', 'Niyor', 'Paloh', 'Rengam', 'Renggam', 'Simpang Rengam', 'Ulu Benut'],
            'Kulai' => ['Bandar Kulai', 'Bandar Tenggara', 'Bukit Batu', 'Gugusan Taib Andak', 'Kulai', 'Sedenak', 'Senai'],
            'Muar' => ['Ayer Hitam', 'Bandar Maharani', 'Bukit Gambir', 'Bukit Kepong', 'Bukit Pasir', 'Jalan Bakri', 'Jorak', 'Lenga', 'Muar', 'Pagoh', 'Panchor', 'Parit Bakar', 'Parit Jawa', 'Sri Menanti', 'Sungai Balang', 'Sungai Mati', 'Sungai Raya', 'Sungai Terap'],
            'Kota Tinggi' => ['Bandar Kota Tinggi', 'Bandar Penawar', 'Johor Lama', 'Kambau', 'Kota Tinggi', 'Pantai Timur', 'Pengerang', 'Sedili Besar', 'Sedili Kechil', 'Tanjung Surat', 'Ulu Sungai Johor', 'Ulu Sungei Sedili Besar'],
            'Segamat' => ['Bandar Segamat', 'Batu Anam', 'Bekok', 'Buloh Kasap', 'Chaah', 'Gemas', 'Gemereh', 'Jabi', 'Jementah', 'Labis', 'Pogoh', 'Segamat', 'Sermin', 'Sungai Segamat'],
            'Pontian' => ['Air Masin', 'Api-Api', 'Ayer Baloi', 'Bandar Pontian', 'Benut', 'Jeram Batu', 'Kukup', 'Pekan Nenas', 'Pengkalan Raja', 'Pontian', 'Rimba Terjun', 'Serkat', 'Sungai Karang', 'Sungei Pinggan'],
            'Tangkak' => ['Bandar Tangkak', 'Bukit Serampang', 'Gerisek', 'Grisek', 'Kesang', 'Kundang', 'Serom', 'Tangkak'],
            'Mersing' => ['Ayer Tawar 2', 'Bandar Mersing', 'Endau', 'Jemaluang', 'Lenggor', 'Mersing', 'Padang Endau', 'Penyabong', 'Pulau Aur', 'Pulau Babi', 'Pulau Pemanggil', 'Pulau Satu', 'Pulau Sibu', 'Pulau Tinggi', 'Sembrong', 'Tenggaroh', 'Tenglu', 'Triang'],
            'Kuala Muda' => ['Bandar Sungai Petani', 'Bedong', 'Bujang', 'Bukit Meriam', 'Gurun', 'Haji Kudong', 'Kota', 'Kota Kuala Muda', 'Kuala', 'Merbok', 'Pekula', 'Pinang Tunggal', 'Rantau Panjang', 'Semeling', 'Sidam Kiri', 'Simpor', 'Sungai Pasir', 'Sungai Petani'],
            'Kota Setar' => ['Alor Malai', 'Alor Setar', 'Anak Bukit', 'Bandar Alor Setar', 'Derga', 'Gunong', 'Kangkong', 'Kepala Batas', 'Kodiang', 'Kota Sarang Semut', 'Kota Setar', 'Kuala Kedah', 'Kubang Rotan', 'Langgar', 'Lengkuas', 'Lepai', 'Limbong', 'Padang Hang', 'Padang Lalang', 'Pengkalan Kundor', 'Sala Kechik', 'Simpang Empat', 'Sungai Baharu', 'Tajar', 'Tebengau', 'Telaga Mas', 'Titi Gajah'],
            'Kulim' => ['Bagan Sena', 'Bandar Kulim', 'Junjong', 'Karangan', 'Kulim', 'Lunas', 'Mahang', 'Naga Lilit', 'Padang China', 'Padang Meha', 'Padang Serai', 'Sedim', 'Sidam Kanan', 'Sungai Seluang', 'Sungai Ular', 'Terap'],
            'Kubang Pasu' => ['Ah', 'Ayer Hitam', 'Bandar Darulaman', 'Bandar Jitra', 'Binjal', 'Bukit Kayu Hitam', 'Bukit Tinggi', 'Changloon', 'Gelong', 'Husba', 'Jeram', 'Jerlun', 'Jitra', 'Kepelu', 'Kubang Pasu', 'Malau', 'Naga', 'Padang Perahu', 'Pelubang', 'Pering', 'Putat', 'Sanglang', 'Sungai Laka', 'Temin', 'Tunjang', 'Universiti Utara Malaysia', 'Wang Tepus'],
            'Baling' => ['Bakai', 'Baling', 'Bandar Baling', 'Bongor', 'Kuala Ketil', 'Kuala Pegang', 'Kupang', 'Pulai', 'Siong', 'Tawar', 'Teloi Kanan'],
            'Pendang' => ['Ayer Puteh', 'Bandar Pendang', 'Bukit Paya', 'Guar Kepayang', 'Padang Kerbau', 'Padang Peliang', 'Padang Pusing', 'Pendang', 'Rambai', 'Tobiar'],
            'Langkawi' => ['Ayer Hangat', 'Bohor', 'Kedawang', 'Kuah', 'Langkawi', 'Padang Mat Sirat', 'Ulu Melaka'],
            'Yan' => ['Dulang', 'Guar Chempedak', 'Sala Besar', 'Singkir', 'Sungai Daun', 'Yan', 'Yan Kechil'],
            'Sik' => ['Jeniang', 'Jeneri', 'Pekan Sik', 'Sik', 'Sok'],
            'Padang Terap' => ['Batang Tunggang Kanan', 'Batang Tunggang Kiri', 'Belimbing Kanan', 'Belimbing Kiri', 'Kuala Nerang', 'Kurong Hitam', 'Padang Temak', 'Padang Terap Kanan', 'Padang Terap Kiri', 'Pedu', 'Tekai', 'Tolak'],
            'Pokok Sena' => ['Bukit Lada', 'Derang', 'Gajah Mati', 'Jabi', 'Lesong', 'Pekan Pokok Sena', 'Pokok Sena', 'Tualang'],
            'Bandar Baharu' => ['Bagan Samak', 'Bandar Baharu', 'Kuala Selama', 'Permatang Pasir', 'Relau', 'Serdang', 'Sungai Batu', 'Sungai Kechil'],
            'Kota Bharu' => ['Badang', 'Banggu', 'Bandar Baru Tunjong', 'Bandar Kota Bharu', 'Baung', 'Bayang', 'Beta Hilir', 'Beta Hulu', 'Beting', 'Bunut Payong', 'Demit', 'Guntong', 'Kadok', 'Kem Desa Pahlawan', 'Kemumin', 'Ketereh', 'Kijang', 'Kota', 'Kota Bharu', 'Kubang Kerian', 'Langgar', 'Lundang', 'Mentuan', 'Mulong', 'Panji', 'Pendek', 'Pengkalan Chepa', 'Peringat', 'Perol', 'Pulau Kundor', 'Salor', 'Sering', 'Tiong', 'Wakaf Stan'],
            'Pasir Mas' => ['Alor Buloh', 'Alor Pasir', 'Apam', 'Bakong', 'Bandar Pasir Mas', 'Bechah Menerong', 'Bechah Palas', 'Bechah Semak', 'Bukit Tuku', 'Chetok', 'Gelam', 'Gua', 'Gual Nering', 'Gual Periok', 'Jabo', 'Jejawi', 'Kala', 'Kangkong', 'Kasa', 'Kedondong', 'Kenak', 'Kerasak', 'Kiat', 'Kuala Lemal', 'Kubang Batang', 'Kubang Bemban', 'Kubang Gatal', 'Kubang Gendang', 'Kubang Ketam', 'Kubang Sepat', 'Kubang Terap', 'Lalang', 'Lubok Anching', 'Lubok Gong', 'Lubok Kawah', 'Lubok Setol', 'Lubok Tapah', 'Meranti', 'Padang Embon', 'Paloh', 'Pasir Mas', 'Rantau Panjang', 'Sakar', 'Tasik Berangan', 'Teliar', 'Tendong', 'Tok Sangkot', 'Tok Uban'],
            'Tumpat' => ['Bandar Tumpat', 'Bechah Resak', 'Bunohan', 'Bunut Sarang Burong', 'Chenderong Batu', 'Cherang Melintang', 'Geting', 'Jal', 'Kampong Laut', 'Kelaboran', 'Ketil', 'Kok Keli', 'Kutang', 'Mak Neralang', 'Morak', 'Palekbang', 'Pasir Pekan', 'Periok', 'Pulau Besar', 'Selehong Selatan', 'Selehong Utara', 'Simpangan', 'Sungai Pinang', 'Tabar', 'Talak', 'Telok Renjuna', 'Tujoh', 'Tumpat', 'Wakaf Bharu'],
            'Bachok' => ['Alor Bakat', 'Bachok', 'Bandar Bachok', 'Bator', 'Chap', 'Cherang Hangus', 'Cherang Ruku', 'Gunong', 'Kemasin', 'Kuau', 'Kubang Telaga', 'Lubok Tembesu', 'Mak Lipah', 'Melawi', 'Melor', 'Nipah', 'Pak Pura', 'Pauh Sembilan', 'Perupok', 'Repek', 'Rusa', 'Senak', 'Serdang', 'Takang', 'Tanjong', 'Telok Mesira', 'Telong', 'Tepus'],
            'Tanah Merah' => ['Bandar Tanah Merah', 'Batang Merbau', 'Bendang Nyior', 'Bukit Durian', 'Jedok', 'Kuala Paku', 'Lawang', 'Maka', 'Nibong', 'Pasir Ganda', 'Rambai', 'Sokor', 'Tanah Merah', 'Tebing Tinggi', 'Ulu Kusial'],
            'Pasir Puteh' => ['Bandar Pasir Puteh', 'Banggol Setol', 'Batu Sebutir', 'Berangan', 'Bukit Abal Barat', 'Bukit Abal Timor', 'Bukit Merbau', 'Bukit Tanah', 'Changgai', 'Cherang Ruku', 'Gong Chengal', 'Gong Datok Barat', 'Gong Datok Timor', 'Gong Garu', 'Gong Kulim', 'Gong Nangka', 'Jeram', 'Jerus', 'Kampong Wakaf', 'Kandis', 'Kolam Tembesu', 'Merbol', 'Merkang', 'Padang Pak Amat', 'Pasir Puteh', 'Permatang Sungkai', 'Seligi', 'Selising', 'Semerak', 'Tasik', 'Telipok'],
            'Kuala Krai' => ['Bandar Kuala Krai', 'Batu Mengkebang', 'Dabong', 'Enggong', 'Gajah', 'Kandek', 'Kenor', 'Kuala Geris', 'Kuala Krai', 'Kuala Nal', 'Kuala Pahi', 'Kuala Pergau', 'Kuala Stong', 'Mambong', 'Manek Urai', 'Manjor', 'Olak Jeram', 'Telekong'],
            'Machang' => ['Bagan', 'Bakar', 'Bandar Machang', 'Dewan', 'Gading Galoh', 'Jakar', 'Joh', 'Kelaweh', 'Kerawang', 'Kerilla', 'Kuala Kerak', 'Labok', 'Limau Hantu', 'Machang', 'Padang Kemunchut', 'Pek', 'Pemanok', 'Pulai Chondong', 'Raja', 'Temangan', 'Tengah', 'Tok Bok', 'Ulu Sat'],
            'Gua Musang' => ['Bandar Gua Musang', 'Batu Papan', 'Chiku', 'Dabong', 'Galas', 'Gua Musang', 'Ketil', 'Kuala Sungai', 'Limau Kasturi', 'Pulai', 'Relai', 'Renok', 'Ulu Nenggiri'],
            'Jeli' => ['Ayer Lanas', 'Bandar Jeli', 'Belimbing', 'Bunga Tanjong', 'Jeli', 'Jeli Tepi Sungai', 'Kalai', 'Kuala Balah', 'Lubok Bongor'],
            'Lojing' => ['Betis', 'Blau', 'Hau', 'Hendrop', 'Kuala Betis', 'Tuel'],
            'Melaka Tengah' => ['Ayer Keroh', 'Alai', 'Ayer Molek', 'Bachang', 'Balai Panjang', 'Bandaraya Melaka', 'Batu Berendam', 'Bertam', 'Bukit Baru', 'Bukit Katil', 'Bukit Lintang', 'Bukit Piatu', 'Bukit Rambai', 'Cheng', 'Duyong', 'Kandang', 'Klebang Besar', 'Klebang Kechil', 'Krubong', 'Melaka', 'Padang Temu', 'Paya Rumput', 'Pernu', 'Pringgit', 'Semabok', 'Sungai Udang', 'Sungei Udang', 'Tangga Batu', 'Tanjong Kling', 'Tanjong Minyak', 'Telok Mas', 'Ujong Pasir'],
            'Alor Gajah' => ['Alor Gajah', 'Asahan', 'Ayer Pa\'abas', 'Bandar Alor Gajah', 'Belimbing', 'Beringin', 'Brisu', 'Durian Tunggal', 'Gadek', 'Kelemak', 'Kemuning', 'Kuala Linggi', 'Kuala Sungai Baru', 'Kuala Sungei Baru', 'Lendu', 'Machap', 'Malaka Pindah', 'Masjid Tanah', 'Melekek', 'Padang Sebang', 'Parit Melana', 'Pegoh', 'Pulau Sebang', 'Ramuan China Besar', 'Ramuan China Kechil', 'Rembia', 'Sungei Baru Ilir', 'Sungei Baru Tengah', 'Sungei Baru Ulu', 'Sungei Buloh', 'Sungei Petai', 'Sungei Siput', 'Taboh Naning', 'Tanjong Rimau', 'Tebong'],
            'Jasin' => ['Ayer Keroh', 'Ayer Panas', 'Asahan', 'Bandar Jasin', 'Batang Malaka', 'Bemban', 'Bukit Senggeh', 'Chabau', 'Chin Chin', 'Chohong', 'Jasin', 'Jus', 'Kesang', 'Merlimau', 'Nyalas', 'Rim', 'Sebatu', 'Selandar', 'Sempang', 'Semujok', 'Serkam', 'Sungei Rambai', 'Tedong', 'Umbai'],
            'Seremban' => ['Ampangan', 'Bandar Enstek', 'Bandar Seremban', 'Labu', 'Lenggeng', 'Mantin', 'Nilai', 'Pantai', 'Rantau', 'Rasah', 'Senawang', 'Seremban', 'Seremban 2', 'Setul'],
            'Jempol' => ['Bahau', 'Bandar Seri Jempol', 'Batu Kikir', 'Jelai', 'Kuala Jempol', 'Pusat Bandar Palong', 'Rompin', 'Serting Hilir', 'Serting Ulu'],
            'Port Dickson' => ['Jimah', 'Linggi', 'Lukut', 'Pasir Panjang', 'Port Dickson', 'Si Rusa'],
            'Tampin' => ['Air Kuning', 'Gemas', 'Gemencheh', 'Keru', 'Repah', 'Tampin', 'Tampin Tengah', 'Tebong'],
            'Kuala Pilah' => ['Ampang Tinggi', 'Johol', 'Juasseh', 'Kepis', 'Kuala Pilah', 'Langkap', 'Parit Tinggi', 'Pilah', 'Sri Menanti', 'Terachi', 'Ulu Jempol', 'Ulu Muar'],
            'Rembau' => ['Batu Hampar', 'Bongek', 'Chembong', 'Chengkau', 'Gadong', 'Kota', 'Kundor', 'Legong Ilir', 'Legong Ulu', 'Miku', 'Nerasau', 'Pedas', 'Pilin', 'Rembau', 'Selemak', 'Semerbok', 'Spri', 'Tanjong Kling', 'Titian Bintangor'],
            'Jelebu' => ['Glami Lemi', 'Kenaboi', 'Kuala Klawang', 'Peradong', 'Pertang', 'Simpang Durian', 'Simpang Pertang', 'Tanjong Ipoh', 'Triang Ilir', 'Ulu Klawang', 'Ulu Triang'],
            'Kuantan' => ['Balok', 'Bandar Kuantan', 'Beserah', 'Bukit Goh', 'Bukit Kuin', 'Gambang', 'Gebeng', 'Hulu Kuantan', 'Hulu Lepar', 'Kuala Kuantan', 'Kuantan', 'Penor', 'Sungai Karang', 'Sungai Lembing'],
            'Temerloh' => ['Bangau', 'Jenderak', 'Kerdau', 'Lanchang', 'Lebak', 'Lipat Kajang', 'Mentakab', 'Perak', 'Sanggang', 'Semantan', 'Songsang', 'Temerloh'],
            'Bentong' => ['Bentong', 'Genting Highlands', 'Karak', 'Pelangai', 'Sabai'],
            'Maran' => ['Bandar Tun Abdul Razak', 'Bukit Segumpal', 'Chenor', 'Kertau', 'Luit', 'Lurah Bilut', 'Maran'],
            'Rompin' => ['Endau', 'Keratong', 'Kuala Rompin', 'Muadzam Shah', 'Pontian', 'Rompin', 'Tioman'],
            'Pekan' => ['Bebar', 'Chini', 'Ganchong', 'Kuala Pahang', 'Langgar', 'Lepar', 'Pahang Tua', 'Pekan', 'Penyor', 'Pulau Manis', 'Pulau Rusa', 'Temai'],
            'Bera' => ['Bandar Bera', 'Bera', 'Kemayan', 'Triang'],
            'Raub' => ['Batu Talam', 'Bukit Fraser', 'Dong', 'Gali', 'Hulu Dong', 'Raub', 'Sega', 'Semantan Hulu', 'Teras'],
            'Jerantut' => ['Bandar Pusat Jengka', 'Burau', 'Damak', 'Hulu Cheka', 'Hulu Tembeling', 'Jerantut', 'Kelola', 'Kuala Krau', 'Kuala Tembeling', 'Pedah', 'Pulau Tawar', 'Tebing Tinggi', 'Teh', 'Tembeling'],
            'Lipis' => ['Batu Yon', 'Benta', 'Budu', 'Cheka', 'Dong', 'Gua', 'Hulu Jelai', 'Kechau', 'Kuala Lipis', 'Padang Tengku', 'Penjom', 'Sega', 'Sungai Koyan', 'Tanjung Besar', 'Telang'],
            'Cameron Highlands' => ['Brinchang', 'Hulu Telom', 'Ringlet', 'Tanah Rata'],
            'Kinta' => ['Batu Gajah', 'Belanja', 'Chemor', 'Hulu Kinta', 'Ipoh', 'Kampung Kepayang', 'Kampar', 'Lahat', 'Pusing', 'Sungai Raia', 'Sungai Terap', 'Tambun', 'Tanjong Rambutan', 'Tanjong Tualang', 'Teja', 'Ulu Kinta'],
            'Larut, Matang dan Selama' => ['Asam Kumbang', 'Batu Kurau', 'Bukit Gantang', 'Jebong', 'Kamunting', 'Kuala Sepetang', 'Matang', 'Pengkalan Aor', 'Selama', 'Simpang', 'Sungai Limau', 'Sungai Tinggi', 'Taiping', 'Terong', 'Trong', 'Tupai', 'Ulu Ijok', 'Ulu Selama'],
            'Manjung' => ['Ayer Tawar', 'Beruas', 'Bruas', 'Changkat Keruing', 'Lekir', 'Lumut', 'Pangkor', 'Pantai Remis', 'Pengkalan Baharu', 'Seri Manjung', 'Sitiawan', 'TLDM Lumut'],
            'Hilir Perak' => ['Changkat Jong', 'Chikus', 'Durian Sebatang', 'Labu Kubong', 'Langkap', 'Sungai Durian', 'Sungai Manik', 'Sungai Sumun', 'Teluk Intan'],
            'Kerian' => ['Bagan Serai', 'Bagan Tiang', 'Batu Kurau', 'Beriah', 'Gunong Semanggol', 'Kuala Kurau', 'Parit Buntar', 'Selinsing', 'Simpang Ampat Semanggol', 'Tanjong Piandang'],
        ];
    }
}

