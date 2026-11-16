/**
 * ApexVote – Kolkata College Directory
 * Educational institutions of Kolkata, West Bengal, India
 * 
 * Notice: This is a demonstration educational directory representing collegiate 
 * institutions across Kolkata for academic project simulation. No official 
 * partnership or administrative endorsement is implied.
 */

window.KolkataCollegeDirectory = {
    institutions: [
        {
            id: 'cu',
            fullName: 'University of Calcutta',
            shortName: 'CU',
            category: 'Public State University',
            established: 1857,
            campusLocation: 'College Street, Central Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.caluniv.ac.in',
            description: 'Established in 1857, the University of Calcutta is one of India\'s oldest and most renowned multidisciplinary universities, spanning collegiate campuses across the metropolitan region.',
            verified: true
        },
        {
            id: 'ju',
            fullName: 'Jadavpur University',
            shortName: 'JU',
            category: 'Public State University',
            established: 1955,
            campusLocation: 'Raja S.C. Mallick Road, Jadavpur',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'http://www.jaduniv.edu.in',
            description: 'Evolving from the National Council of Education established during the Swadeshi movement, Jadavpur University is globally recognized for engineering, sciences, and humanities.',
            verified: true
        },
        {
            id: 'pu',
            fullName: 'Presidency University',
            shortName: 'PU',
            category: 'Public State University',
            established: 1817,
            campusLocation: 'College Street, Central Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.presiuniv.ac.in',
            description: 'Originating as Hindoo College in 1817 and later Presidency College, this premier institution played an iconic role in the Bengal Renaissance and modern Indian higher education.',
            verified: true
        },
        {
            id: 'sxcc',
            fullName: 'St. Xavier\'s College (Autonomous)',
            shortName: 'SXCC',
            category: 'Autonomous College',
            established: 1860,
            campusLocation: 'Mother Teresa Sarani (Park Street)',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.sxccal.edu',
            description: 'Founded by the Society of Jesus in 1860, St. Xavier\'s College is an autonomous premier Jesuit institution renowned for commerce, arts, sciences, and student governance.',
            verified: true
        },
        {
            id: 'ac',
            fullName: 'Asutosh College',
            shortName: 'AC',
            category: 'Affiliated College (CU)',
            established: 1916,
            campusLocation: 'Shyama Prasad Mukherjee Road, Hazra',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://asutoshcollege.in',
            description: 'Founded by educationist Sir Asutosh Mukherjee, it was the first college in Bengal to provide intermediate and degree courses in South Kolkata.',
            verified: true
        },
        {
            id: 'scc',
            fullName: 'Scottish Church College',
            shortName: 'SCC',
            category: 'Autonomous Minority College',
            established: 1830,
            campusLocation: 'Urquhart Square, Hedua, North Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.scottishchurch.ac.in',
            description: 'Established in 1830 by Rev. Alexander Duff, Scottish Church College is one of the oldest continuing academic institutions in the subcontinent, notable as Swami Vivekananda\'s alma mater.',
            verified: true
        },
        {
            id: 'mac',
            fullName: 'Maulana Azad College',
            shortName: 'MAC',
            category: 'Government College',
            established: 1926,
            campusLocation: 'Rafi Ahmed Kidwai Road, Central Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://maulanaazadcollegekolkata.ac.in',
            description: 'A prestigious government-administered co-educational institution situated in central Kolkata offering honors disciplines across arts, humanities, and natural sciences.',
            verified: true
        },
        {
            id: 'besc',
            fullName: 'The Bhawanipur Education Society College',
            shortName: 'BESC',
            category: 'Linguistic Minority College',
            established: 1966,
            campusLocation: 'Elgin Road, South Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://thebges.edu.in',
            description: 'A prominent South Kolkata college affiliated with Calcutta University, widely known for business studies, commerce, information technology, and dynamic campus initiatives.',
            verified: true
        },
        {
            id: 'bc',
            fullName: 'Bethune College',
            shortName: 'BC',
            category: 'Government Women\'s College',
            established: 1879,
            campusLocation: 'Bidhan Sarani, Hedua, North Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'http://bethunecollege.ac.in',
            description: 'Pioneered by John Elliot Drinkwater Bethune, it is the first higher education college for women in Asia, producing trailblazing graduates including Kadambini Ganguly and Chandramukhi Basu.',
            verified: true
        },
        {
            id: 'lc',
            fullName: 'Loreto College',
            shortName: 'LC',
            category: 'Catholic Women\'s College',
            established: 1912,
            campusLocation: 'Middleton Row, Central Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.loretocollege.in',
            description: 'Founded by the Institute of the Blessed Virgin Mary (Loreto Sisters), Loreto College has dedicated over a century to women\'s tertiary education and liberal arts excellence.',
            verified: true
        },
        {
            id: 'lbc',
            fullName: 'Lady Brabourne College',
            shortName: 'LBC',
            category: 'Government Women\'s College',
            established: 1939,
            campusLocation: 'Suhrawardy Avenue, Park Circus',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.ladybrabourne.com',
            description: 'Established in 1939 by the Government of Bengal, Lady Brabourne is one of Kolkata\'s leading institutions dedicated to empowering women through honors education in science and arts.',
            verified: true
        },
        {
            id: 'gccba',
            fullName: 'Goenka College of Commerce and Business Administration',
            shortName: 'GCCBA',
            category: 'Government Commerce College',
            established: 1905,
            campusLocation: 'Bipin Behari Ganguly Street, Bowbazar',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'http://goenkacollege.net',
            description: 'Among the oldest and most distinguished commerce and business management colleges in India, functioning under the Higher Education Department of West Bengal.',
            verified: true
        },
        {
            id: 'ssc',
            fullName: 'Shri Shikshayatan College',
            shortName: 'SSC',
            category: 'Linguistic Minority Women\'s College',
            established: 1955,
            campusLocation: 'Lord Sinha Road, Central Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://shrishikshayatancollege.org',
            description: 'Founded in 1955 under the Marwari Balika Vidyalaya Society, Shri Shikshayatan provides undergraduate and postgraduate programs for women in commerce, media, arts, and science.',
            verified: true
        },
        {
            id: 'tiu',
            fullName: 'Techno India University',
            shortName: 'TIU',
            category: 'Private University',
            established: 2012,
            campusLocation: 'EM-4, Salt Lake, Sector V',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.technoindiauniversity.ac.in',
            description: 'Situated in Kolkata\'s IT hub, Techno India University delivers academic and research programs in engineering, technology, computer applications, and business administration.',
            verified: true
        },
        {
            id: 'hitk',
            fullName: 'Heritage Institute of Technology',
            shortName: 'HITK',
            category: 'Autonomous Engineering Institute',
            established: 2001,
            campusLocation: 'Chowbaga Road, Anandapur, East Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.heritageit.edu',
            description: 'An autonomous engineering and technical institute affiliated with MAKAUT, established by the Kalyan Bharti Trust in the Anandapur educational enclave.',
            verified: true
        },
        {
            id: 'iimc',
            fullName: 'Indian Institute of Management Calcutta',
            shortName: 'IIMC',
            category: 'Institute of National Importance',
            established: 1961,
            campusLocation: 'Diamond Harbour Road, Joka',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://www.iimcal.ac.in',
            description: 'The first Indian Institute of Management established in 1961, situated on a sprawling 135-acre campus in Joka, holding triple-crown international business accreditation.',
            verified: true
        },
        {
            id: 'au',
            fullName: 'Aliah University',
            shortName: 'AU',
            category: 'State University',
            established: 2008,
            campusLocation: 'Action Area II, New Town & Park Circus',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://aliah.ac.in',
            description: 'Tracing its historical lineage to the Mohammedan College of Calcutta (1780), upgraded to a modern state university offering professional, engineering, and cultural curricula.',
            verified: true
        },
        {
            id: 'snu',
            fullName: 'Sister Nivedita University',
            shortName: 'SNU',
            category: 'Private University',
            established: 2017,
            campusLocation: 'DG 1/2, Action Area I, New Town',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://snuniv.ac.in',
            description: 'Named in honor of social pioneer Sister Nivedita, this private university in New Town Kolkata focuses on multidisciplinary modern sciences, design, and humanities.',
            verified: true
        },
        {
            id: 'jis',
            fullName: 'JIS University',
            shortName: 'JIS',
            category: 'Private University',
            established: 2014,
            campusLocation: 'Nilgunj Road, Agarpara, Greater Kolkata',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://jisuniversity.ac.in',
            description: 'A premier private educational university in the Greater Kolkata perimeter offering engineering, pharmaceutical sciences, law, and management degree programs.',
            verified: true
        },
        {
            id: 'iem',
            fullName: 'Institute of Engineering and Management',
            shortName: 'IEM',
            category: 'Private Engineering & Management Institute',
            established: 1989,
            campusLocation: 'Sector V, Salt Lake Electronics Complex',
            city: 'Kolkata',
            state: 'West Bengal',
            officialWebsite: 'https://iem.edu.in',
            description: 'Established in 1989 as the earliest private engineering institute in West Bengal, located right in the heart of the Salt Lake IT and business hub.',
            verified: true
        }
    ],

    /**
     * Get institution by ID
     */
    getById: function(id) {
        if (!id) return null;
        return this.institutions.find(item => item.id.toLowerCase() === id.toLowerCase()) || null;
    },

    /**
     * Get all verified institutions
     */
    getAll: function() {
        return this.institutions;
    },

    /**
     * Get array of college entries mapped to the 54 Rubik's cube exterior tiles
     * 6 faces x 9 tiles per face = 54 tiles.
     * Generates a curated, deterministic layout so each face has distinct, real institutions.
     */
    getTilesDistribution: function() {
        const list = this.institutions;
        const totalTiles = 54;
        const distribution = [];

        // Curated ordering ensures prime diversity across all 6 faces
        const primaryOrder = [
            'cu', 'ju', 'pu', 'sxcc', 'ac', 'scc', 'mac', 'besc', 'bc',      // Face 0 (Right, +X)
            'lc', 'lbc', 'gccba', 'ssc', 'tiu', 'hitk', 'iimc', 'au', 'snu',  // Face 1 (Left, -X)
            'cu', 'sxcc', 'ju', 'pu', 'hitk', 'iimc', 'besc', 'tiu', 'iem',   // Face 2 (Top, +Y)
            'ac', 'scc', 'bc', 'lc', 'lbc', 'mac', 'gccba', 'ssc', 'jis',    // Face 3 (Bottom, -Y)
            'ju', 'cu', 'pu', 'sxcc', 'iimc', 'hitk', 'tiu', 'snu', 'iem',   // Face 4 (Front, +Z)
            'besc', 'ac', 'scc', 'bc', 'lbc', 'gccba', 'mac', 'ssc', 'au'    // Face 5 (Back, -Z)
        ];

        for (let i = 0; i < totalTiles; i++) {
            const id = primaryOrder[i] || list[i % list.length].id;
            const college = this.getById(id) || list[0];
            distribution.push({
                tileIndex: i,
                faceIndex: Math.floor(i / 9),
                gridIndex: i % 9,
                college: college
            });
        }

        return distribution;
    }
};
