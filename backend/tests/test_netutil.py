from types import SimpleNamespace

import pytest

from app.services.netutil import DEFAULT_LAB_CIDR, next_host, parse_cidr, readdress_slash8


def test_default_private_range_is_slash8() -> None:
    assert DEFAULT_LAB_CIDR == "10.0.0.0/8"
    assert parse_cidr(DEFAULT_LAB_CIDR).prefixlen == 8


def test_parse_cidr_rejects_reserved_and_tiny_prefixes() -> None:
    with pytest.raises(ValueError, match="reserved"):
        parse_cidr("0.0.0.0/8")
    with pytest.raises(ValueError, match="/8"):
        parse_cidr("10.0.0.0/7")
    with pytest.raises(ValueError, match="/30"):
        parse_cidr("10.0.0.0/32")


def test_admin_may_create_smaller_than_slash8() -> None:
    net = parse_cidr("10.20.0.0/16")
    assert str(net) == "10.20.0.0/16"


def test_next_host_skips_gateway_and_used() -> None:
    network = SimpleNamespace(cidr="10.0.0.0/8")
    assert next_host(network, set()) == "10.0.0.2"
    assert next_host(network, {"10.0.0.2", "10.0.0.3"}) == "10.0.0.4"


def test_readdress_slash8_keeps_last_octet() -> None:
    ifaces = [
        SimpleNamespace(ipv4="10.142.0.2"),
        SimpleNamespace(ipv4="10.142.0.3"),
        SimpleNamespace(ipv4="10.142.0.4"),
    ]
    network = SimpleNamespace(cidr="10.142.0.0/24", interfaces=ifaces)
    changes = readdress_slash8(network)
    assert network.cidr == "10.0.0.0/8"
    assert [i.ipv4 for i in ifaces] == ["10.0.0.2", "10.0.0.3", "10.0.0.4"]
    assert ("10.142.0.2", "10.0.0.2") in changes
