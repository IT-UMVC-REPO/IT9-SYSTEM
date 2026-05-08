<?php

namespace App\Enums;

class TagumCoordinate
{
    public const CENTER_LAT = 7.4479;

    public const CENTER_LNG = 125.8090;

    /**
     * @return list<array{lat: float, lng: float, address: string}>
     */
    public static function namedPlaces(): array
    {
        return [
            ['lat' => 7.4479, 'lng' => 125.8090, 'address' => 'Tagum City Public Market, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4432, 'lng' => 125.8074, 'address' => 'NCCC Mall, Apokon Road, Tagum City'],
            ['lat' => 7.4401, 'lng' => 125.8115, 'address' => 'KCC Mall of Tagum, JP Laurel Highway, Tagum City'],
            ['lat' => 7.4468, 'lng' => 125.8095, 'address' => 'Tagum City Center, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4487, 'lng' => 125.8056, 'address' => 'Magugpo Poblacion Barangay Center, Tagum City'],
            ['lat' => 7.4250, 'lng' => 125.8275, 'address' => 'Apokon Barangay Center, Tagum City'],
            ['lat' => 7.4084, 'lng' => 125.7788, 'address' => 'Canocotan Barangay Center, Tagum City'],
            ['lat' => 7.4907, 'lng' => 125.7936, 'address' => 'Cuambogan Barangay Center, Tagum City'],
            ['lat' => 7.4765, 'lng' => 125.8054, 'address' => 'La Filipina Barangay Center, Tagum City'],
            ['lat' => 7.3484, 'lng' => 125.7756, 'address' => 'Liboganon Barangay Center, Tagum City'],
            ['lat' => 7.3832, 'lng' => 125.8084, 'address' => 'Madaum Barangay Center, Tagum City'],
            ['lat' => 7.4725, 'lng' => 125.8403, 'address' => 'Magdum Barangay Center, Tagum City'],
            ['lat' => 7.4496, 'lng' => 125.8153, 'address' => 'Magugpo East Barangay Center, Tagum City'],
            ['lat' => 7.4595, 'lng' => 125.8149, 'address' => 'Magugpo North Barangay Center, Tagum City'],
            ['lat' => 7.4474, 'lng' => 125.7958, 'address' => 'Magugpo South Barangay Center, Tagum City'],
            ['lat' => 7.4570, 'lng' => 125.8026, 'address' => 'Magugpo West Barangay Center, Tagum City'],
            ['lat' => 7.4602, 'lng' => 125.7849, 'address' => 'Mankilam Barangay Center, Tagum City'],
            ['lat' => 7.4908, 'lng' => 125.8527, 'address' => 'New Balamban Barangay Center, Tagum City'],
            ['lat' => 7.5000, 'lng' => 125.8163, 'address' => 'Nueva Fuerza Barangay Center, Tagum City'],
            ['lat' => 7.4804, 'lng' => 125.7516, 'address' => 'Pagsabangan Barangay Center, Tagum City'],
            ['lat' => 7.4824, 'lng' => 125.8752, 'address' => 'Pandapan Barangay Center, Tagum City'],
            ['lat' => 7.4923, 'lng' => 125.8261, 'address' => 'San Agustin Barangay Center, Tagum City'],
            ['lat' => 7.3930, 'lng' => 125.7870, 'address' => 'San Isidro Barangay Center, Tagum City'],
            ['lat' => 7.4435, 'lng' => 125.7747, 'address' => 'San Miguel Barangay Center, Tagum City'],
            ['lat' => 7.4350, 'lng' => 125.8001, 'address' => 'Visayan Village Barangay Center, Tagum City'],
            ['lat' => 7.3792, 'lng' => 125.7548, 'address' => 'Bincungan Barangay Center, Tagum City'],
            ['lat' => 7.3548, 'lng' => 125.7559, 'address' => 'Busaon Barangay Center, Tagum City'],
            ['lat' => 7.4493, 'lng' => 125.8119, 'address' => 'Gaisano Mall of Tagum, National Highway, Tagum City'],
            ['lat' => 7.4517, 'lng' => 125.8135, 'address' => 'NCCC Mall of Tagum, Apokon Road, Tagum City'],
            ['lat' => 7.4453, 'lng' => 125.8108, 'address' => 'Gaisano Grand Mall of Tagum, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4406, 'lng' => 125.8264, 'address' => 'New Tagum City Hall, Apokon, Tagum City'],
            ['lat' => 7.4569, 'lng' => 125.7822, 'address' => 'Davao del Norte Provincial Capitol, Mankilam, Tagum City'],
            ['lat' => 7.4411, 'lng' => 125.7997, 'address' => 'Christ the King Cathedral, Magugpo South, Tagum City'],
            ['lat' => 7.4501, 'lng' => 125.8069, 'address' => 'Rotary Park, Quezon Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4469, 'lng' => 125.8039, 'address' => 'Tagum City Freedom Park, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4188, 'lng' => 125.8249, 'address' => 'Energy Park, Apokon, Tagum City'],
            ['lat' => 7.4453, 'lng' => 125.8094, 'address' => 'Tagum City Proper, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4611, 'lng' => 125.7994, 'address' => 'Tagum City Public Market and Terminal, Mankilam, Tagum City'],
            ['lat' => 7.4492, 'lng' => 125.8062, 'address' => 'Rizal Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4492, 'lng' => 125.8105, 'address' => 'Osmena Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4465, 'lng' => 125.8070, 'address' => 'Bonifacio Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4474, 'lng' => 125.8082, 'address' => 'Magsaysay Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4504, 'lng' => 125.8074, 'address' => 'Quezon Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4470, 'lng' => 125.8043, 'address' => 'Arellano Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4469, 'lng' => 125.8122, 'address' => 'Sobrecary Street, Magugpo East, Tagum City'],
            ['lat' => 7.4448, 'lng' => 125.8078, 'address' => 'Lapu-Lapu Street, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4436, 'lng' => 125.8008, 'address' => 'Mabini Street, Magugpo South, Tagum City'],
            ['lat' => 7.4489, 'lng' => 125.8050, 'address' => 'Pioneer Avenue, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4470, 'lng' => 125.8112, 'address' => 'Davao-Agusan National Highway, Magugpo East, Tagum City'],
            ['lat' => 7.4338, 'lng' => 125.8196, 'address' => 'Apokon Road, Apokon, Tagum City'],
            ['lat' => 7.4414, 'lng' => 125.7999, 'address' => 'Doctor Juan Gonzales Avenue, Magugpo South, Tagum City'],
            ['lat' => 7.4569, 'lng' => 125.7829, 'address' => 'Capitol Road, Mankilam, Tagum City'],
            ['lat' => 7.4115, 'lng' => 125.7817, 'address' => 'Tagum-Panabo Circumferential Road, Canocotan, Tagum City'],
            ['lat' => 7.4074, 'lng' => 125.7831, 'address' => 'Canocotan Road, Canocotan, Tagum City'],
            ['lat' => 7.4142, 'lng' => 125.7894, 'address' => 'Malagamot Road, Canocotan, Tagum City'],
            ['lat' => 7.4541, 'lng' => 125.8196, 'address' => 'Dilawan Road, Magugpo East, Tagum City'],
            ['lat' => 7.4584, 'lng' => 125.8127, 'address' => 'Magsaysay Avenue, Magugpo North, Tagum City'],
            ['lat' => 7.4459, 'lng' => 125.8029, 'address' => 'Malinawon Road, Magugpo Poblacion, Tagum City'],
            ['lat' => 7.4562, 'lng' => 125.8020, 'address' => 'Dalisay-Gante Avenue, Magugpo West, Tagum City'],
            ['lat' => 7.4217, 'lng' => 125.8252, 'address' => 'Macario Bermudez Road, Apokon, Tagum City'],
            ['lat' => 7.4372, 'lng' => 125.8305, 'address' => 'Tagum City Diversion Road, Apokon, Tagum City'],
            ['lat' => 7.4554, 'lng' => 125.7855, 'address' => 'Tourism Complex, Mankilam, Tagum City'],
            ['lat' => 7.4557, 'lng' => 125.7840, 'address' => 'Palm City, Mankilam, Tagum City'],
            ['lat' => 7.4535, 'lng' => 125.7816, 'address' => 'Margarita Subdivision, Mankilam, Tagum City'],
            ['lat' => 7.4630, 'lng' => 125.7789, 'address' => 'Capitol Homes Subdivision, Mankilam, Tagum City'],
            ['lat' => 7.4648, 'lng' => 125.7892, 'address' => 'Bria Homes Tagum, Mankilam, Tagum City'],
            ['lat' => 7.4749, 'lng' => 125.8052, 'address' => 'La Filipina Barangay Proper, Tagum City'],
            ['lat' => 7.4991, 'lng' => 125.8164, 'address' => 'Nueva Fuerza Barangay Proper, Tagum City'],
            ['lat' => 7.4612, 'lng' => 125.7860, 'address' => 'Mankilam Palm City Road, Tagum City'],
            ['lat' => 7.4418, 'lng' => 125.7989, 'address' => 'Jesus the Resurrected Christ Shrine, Magugpo South, Tagum City'],
        ];
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public static function random(): array
    {
        $place = self::randomPlace();

        return [
            'lat' => $place['lat'],
            'lng' => $place['lng'],
        ];
    }

    /**
     * @return array{lat: float, lng: float, address: string, vendor_address: string}
     */
    public static function randomPlace(): array
    {
        return self::withVendorAddress(fake()->randomElement(self::namedPlaces()));
    }

    /**
     * @return array{lat: float, lng: float, address: string, vendor_address: string}
     */
    public static function place(int $index): array
    {
        $places = self::namedPlaces();

        return self::withVendorAddress($places[$index % count($places)]);
    }

    /**
     * @param  array{lat: float, lng: float, address: string}  $place
     * @return array{lat: float, lng: float, address: string, vendor_address: string}
     */
    private static function withVendorAddress(array $place): array
    {
        return [
            ...$place,
            'vendor_address' => $place['address'],
        ];
    }
}
