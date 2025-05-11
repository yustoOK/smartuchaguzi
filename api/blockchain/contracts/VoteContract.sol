// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

contract VoteContract {
    address public admin;
    
    // Mapping to track if a voter has voted for a position in an election
    mapping(address => mapping(uint256 => mapping(uint256 => bool))) public hasVoted;
    
    // Mapping to store vote counts for each candidate per position
    mapping(uint256 => mapping(uint256 => uint256)) public voteCount;
    
    // Array to store all votes
    Vote[] public votes;

    // Struct to represent a vote with additional details
    struct Vote {
        uint256 electionId;
        address voter;
        uint256 positionId;
        uint256 candidateId;
        uint256 timestamp;
        string candidateName; // Optional, for off-chain use
        string positionName;  // Optional, for off-chain use
    }

    // Events for logging
    event VoteCast(uint256 electionId, address indexed voter, uint256 positionId, uint256 candidateId, string candidateName, string positionName);

    // Modifiers
    modifier onlyAdmin() {
        require(msg.sender == admin, "Not authorized");
        _;
    }

    constructor() {
        admin = msg.sender;
    }

    // Function to cast a vote with additional details
    function castVote(
        uint256 electionId,
        uint256 positionId,
        uint256 candidateId,
        string memory candidateName,
        string memory positionName
    ) external {
        // Ensure voter hasn't voted for this position in this election
        require(!hasVoted[msg.sender][electionId][positionId], "Already voted for this position");
        
        // Ensure position ID is within the limit (1 to 5)
        require(positionId >= 1 && positionId <= 5, "Invalid position ID (must be 1 to 5)");

        // Off-chain validation of election_id, candidate_id, and position_id is assumed
        // This should be handled by the front-end or oracle querying the MySQL database

        // Record the vote
        hasVoted[msg.sender][electionId][positionId] = true;
        voteCount[positionId][candidateId]++;
        votes.push(Vote(electionId, msg.sender, positionId, candidateId, block.timestamp, candidateName, positionName));
        emit VoteCast(electionId, msg.sender, positionId, candidateId, candidateName, positionName);
    }

    // Function to get the vote count for a candidate in a position
    function getVoteCount(uint256 positionId, uint256 candidateId) external view returns (uint256) {
        return voteCount[positionId][candidateId];
    }

    // Function to retrieve all votes for a given election
    function getVotesByElection(uint256 electionId) external view returns (Vote[] memory) {
        uint256 count = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                count++;
            }
        }

        Vote[] memory result = new Vote[](count);
        uint256 index = 0;
        for (uint256 i = 0; i < votes.length; i++) {
            if (votes[i].electionId == electionId) {
                result[index] = votes[i];
                index++;
            }
        }
        return result;
    }
}