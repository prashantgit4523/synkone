import { Table } from "react-bootstrap";

function AccountSubscriptionTab(props) {
    return ( 
        <Table hover>
            <tbody>
                <tr>
                    <td>Subscription Expiration</td>
                    <td>{props.expiry_date}</td>
                </tr>
            </tbody>
        </Table>
     );
}

export default AccountSubscriptionTab;